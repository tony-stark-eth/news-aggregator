<?php

declare(strict_types=1);

namespace App\Digest\Service;

use App\Article\Entity\Article;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class DigestSummaryService
{
    private const string MODEL = 'openrouter/free';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Generate a concise editorial digest for the following news articles grouped by category.
For each category, provide:
- A 1-2 sentence summary of the key themes
- Key takeaways (bullet points)

Articles:
%s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, list<Article>> $groupedArticles
     */
    public function generate(array $groupedArticles): string
    {
        $articleText = $this->formatArticles($groupedArticles);

        try {
            $prompt = sprintf(self::PROMPT_TEMPLATE, $articleText);
            $input = new MessageBag(Message::ofUser($prompt));
            $result = $this->platform->invoke(self::MODEL, $input);
            $content = trim($result->asText());

            if (mb_strlen($content) >= 50) {
                return $content;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('AI digest generation failed: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->ruleBasedFallback($groupedArticles);
    }

    /**
     * @param array<string, list<Article>> $groupedArticles
     */
    private function formatArticles(array $groupedArticles): string
    {
        $parts = [];
        foreach ($groupedArticles as $category => $articles) {
            $parts[] = sprintf('## %s', ucfirst($category));
            foreach ($articles as $article) {
                $summary = $article->getSummary() ?? mb_substr($article->getTitle(), 0, 100);
                $parts[] = sprintf('- %s: %s', $article->getTitle(), $summary);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, list<Article>> $groupedArticles
     */
    private function ruleBasedFallback(array $groupedArticles): string
    {
        $parts = [];
        foreach ($groupedArticles as $category => $articles) {
            $parts[] = sprintf('=== %s ===', strtoupper($category));
            foreach ($articles as $article) {
                $excerpt = $article->getSummary() ?? mb_substr($article->getContentText() ?? $article->getTitle(), 0, 150);
                $parts[] = sprintf("• %s\n  %s\n  %s", $article->getTitle(), $excerpt, $article->getUrl());
            }
            $parts[] = '';
        }

        return implode("\n", $parts);
    }
}
