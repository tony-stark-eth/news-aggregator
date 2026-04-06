<?php

declare(strict_types=1);

namespace App\Article\Mercure;

use App\Article\Entity\Article;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercurePublisherService implements MercurePublisherServiceInterface
{
    private const string TOPIC_ARTICLES = '/articles';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publishArticleCreated(Article $article): void
    {
        $articleId = $article->getId();
        if ($articleId === null) {
            return;
        }

        $payload = $this->buildArticlePayload($article, 'created');
        $this->publish(self::TOPIC_ARTICLES, $payload, $articleId);
    }

    public function publishEnrichmentComplete(Article $article): void
    {
        $articleId = $article->getId();
        if ($articleId === null) {
            return;
        }

        $topic = self::TOPIC_ARTICLES . '/' . $articleId . '/enriched';
        $payload = $this->buildArticlePayload($article, 'enriched');
        $this->publish($topic, $payload, $articleId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArticlePayload(Article $article, string $type): array
    {
        return [
            'type' => $type,
            'articleId' => $article->getId(),
            'title' => $article->getTitle(),
            'summary' => $article->getSummary(),
            'category' => $article->getCategory()?->getName(),
            'categoryColor' => $article->getCategory()?->getColor(),
            'enrichmentMethod' => $article->getEnrichmentMethod()?->value,
            'enrichmentStatus' => $article->getEnrichmentStatus()?->value,
            'score' => $article->getScore(),
            'keywords' => $article->getKeywords(),
            'translations' => $article->getTranslations(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(string $topic, array $payload, int $articleId): void
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->hub->publish(new Update($topic, $json));
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed for article {id}: {error}', [
                'id' => $articleId,
                'error' => $e->getMessage(),
                'topic' => $topic,
            ]);
        }
    }
}
