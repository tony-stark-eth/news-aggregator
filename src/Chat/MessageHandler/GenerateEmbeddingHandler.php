<?php

declare(strict_types=1);

namespace App\Chat\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Message\GenerateEmbeddingMessage;
use App\Chat\Service\EmbeddingServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateEmbeddingHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private EmbeddingServiceInterface $embeddingService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateEmbeddingMessage $message): void
    {
        $article = $this->articleRepository->findById($message->articleId);
        if (! $article instanceof Article) {
            $this->logger->warning('Article not found for embedding generation', [
                'article_id' => $message->articleId,
            ]);

            return;
        }

        $text = $this->buildEmbeddingText($article->getTitle(), $article->getSummary(), $article->getKeywords());
        $embedding = $this->embeddingService->embed($text);

        if ($embedding === null) {
            $this->logger->debug('Embedding generation returned null for article {id}', [
                'id' => $message->articleId,
            ]);

            return;
        }

        $article->setEmbedding($this->encodeEmbedding($embedding));
        $this->articleRepository->save($article, flush: true);

        $this->logger->debug('Stored embedding for article {id}', [
            'id' => $message->articleId,
        ]);
    }

    /**
     * @param list<string>|null $keywords
     */
    private function buildEmbeddingText(string $title, ?string $summary, ?array $keywords): string
    {
        $parts = [$title];

        if ($summary !== null && $summary !== '') {
            $parts[] = $summary;
        }

        if ($keywords !== null && $keywords !== []) {
            $parts[] = implode(' ', $keywords);
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<float> $embedding
     */
    private function encodeEmbedding(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn (float $v): string => (string) $v, $embedding)) . ']';
    }
}
