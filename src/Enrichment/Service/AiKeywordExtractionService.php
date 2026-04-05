<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiKeywordExtractionService implements KeywordExtractionServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const int MAX_KEYWORDS = 8;

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Extract 3-5 key entities (people, organizations, places, topics) from this article. Return ONLY a comma-separated list.

Title: %s
Content: %s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private RuleBasedKeywordExtractionService $ruleBasedFallback,
        private LoggerInterface $logger,
    ) {
    }

    public function extract(string $title, ?string $contentText): array
    {
        try {
            $prompt = sprintf(
                self::PROMPT_TEMPLATE,
                $title,
                mb_substr($contentText ?? '', 0, 1000),
            );

            $input = new MessageBag(Message::ofUser($prompt));
            $response = trim($this->platform->invoke(self::MODEL, $input)->asText());

            $keywords = $this->parseKeywords($response);

            if ($keywords !== []) {
                return $keywords;
            }

            $this->logger->info('AI keyword extraction returned no valid keywords', [
                'response' => $response,
                'model' => self::MODEL,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('AI keyword extraction failed, using rule-based fallback: {error}', [
                'error' => $e->getMessage(),
                'model' => self::MODEL,
            ]);
        }

        return $this->ruleBasedFallback->extract($title, $contentText);
    }

    /**
     * @return list<string>
     */
    private function parseKeywords(string $response): array
    {
        $parts = explode(',', $response);
        $keywords = [];

        foreach ($parts as $part) {
            $keyword = trim($part);
            if ($keyword !== '' && mb_strlen($keyword) <= 100) {
                $keywords[] = $keyword;
            }
        }

        return \array_slice($keywords, 0, self::MAX_KEYWORDS);
    }
}
