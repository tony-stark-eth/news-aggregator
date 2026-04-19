<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelQualityCategory;
use App\Shared\Service\QueueDepthServiceInterface;
use App\Source\Repository\SourceRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PipelineStatusController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly QueueDepthServiceInterface $queueDepthService,
        private readonly ModelQualityTrackerInterface $modelQualityTracker,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/settings/pipelines', name: 'app_pipelines', methods: ['GET'])]
    public function __invoke(): Response
    {
        $health = $this->sourceRepository->countByHealth();
        $enabled = $this->sourceRepository->countEnabled();
        $total = $this->sourceRepository->countAll();

        return $this->controller->render('settings/pipelines.html.twig', [
            'feedStats' => [
                'total' => $total,
                'enabled' => $enabled,
                'disabled' => $total - $enabled,
                'healthy' => $health['healthy'],
                'degraded' => $health['degraded'],
                'failing' => $health['failing'],
            ],
            'sources' => $this->sourceRepository->findAllOrderedByHealth(),
            'fullTextStats' => $this->buildFullTextStats(),
            'enrichmentStats' => $this->articleRepository->getEnrichmentStats(),
            'enrichQueueDepth' => $this->queueDepthService->getEnrichQueueDepth(),
            'modelStats' => $this->modelQualityTracker->getStatsByCategory(ModelQualityCategory::Enrichment),
            'embeddingStats' => $this->articleRepository->getEmbeddingStats(),
            'sentimentStats' => $this->articleRepository->getSentimentStats(),
            'sentimentDistribution' => $this->articleRepository->getSentimentDistribution(
                $this->clock->now()->modify('-7 days'),
            ),
        ]);
    }

    /**
     * @return array{total: int, fetched: int, failed: int, pending: int, skipped: int, noStatus: int, successRate: float}
     */
    private function buildFullTextStats(): array
    {
        $raw = $this->articleRepository->getFullTextStats();
        $attempted = $raw['fetched'] + $raw['failed'];
        $successRate = $attempted > 0 ? round($raw['fetched'] / $attempted * 100, 1) : 0.0;

        return [
            'total' => $raw['total'],
            'fetched' => $raw['fetched'],
            'failed' => $raw['failed'],
            'pending' => $raw['pending'],
            'skipped' => $raw['skipped'],
            'noStatus' => $raw['no_status'],
            'successRate' => $successRate,
        ];
    }
}
