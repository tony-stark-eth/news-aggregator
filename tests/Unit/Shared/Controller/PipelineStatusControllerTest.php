<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;
use App\Shared\Controller\PipelineStatusController;
use App\Shared\Service\QueueDepthServiceInterface;
use App\Source\Repository\SourceRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PipelineStatusControllerTest extends TestCase
{
    private ControllerHelper&MockObject $controllerHelper;

    private SourceRepositoryInterface&MockObject $sourceRepository;

    private ArticleRepositoryInterface&MockObject $articleRepository;

    private QueueDepthServiceInterface&MockObject $queueDepthService;

    private ModelQualityTrackerInterface&MockObject $modelQualityTracker;

    private PipelineStatusController $controller;

    protected function setUp(): void
    {
        $this->controllerHelper = $this->createMock(ControllerHelper::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->queueDepthService = $this->createMock(QueueDepthServiceInterface::class);
        $this->modelQualityTracker = $this->createMock(ModelQualityTrackerInterface::class);

        $this->controller = new PipelineStatusController(
            $this->controllerHelper,
            $this->sourceRepository,
            $this->articleRepository,
            $this->queueDepthService,
            $this->modelQualityTracker,
            new MockClock(new \DateTimeImmutable('2026-04-12 12:00:00')),
        );
    }

    public function testInvokeRendersTemplate(): void
    {
        $this->sourceRepository->method('countByHealth')->willReturn([
            'healthy' => 3,
            'degraded' => 1,
            'failing' => 0,
            'disabled_health' => 0,
        ]);
        $this->sourceRepository->method('countEnabled')->willReturn(4);
        $this->sourceRepository->method('countAll')->willReturn(5);
        $this->sourceRepository->method('findAllOrderedByHealth')->willReturn([]);

        $this->articleRepository->method('getFullTextStats')->willReturn([
            'total' => 100,
            'fetched' => 80,
            'failed' => 5,
            'pending' => 10,
            'skipped' => 3,
            'no_status' => 2,
        ]);
        $this->articleRepository->method('getEnrichmentStats')->willReturn([
            'total' => 100,
            'ai' => 60,
            'rule_based' => 30,
            'pending' => 5,
            'complete' => 90,
            'no_method' => 10,
        ]);
        $this->articleRepository->method('getEmbeddingStats')->willReturn([
            'total' => 100,
            'with_embedding' => 0,
            'without_embedding' => 100,
        ]);
        $this->articleRepository->method('getSentimentStats')->willReturn([
            'total' => 100,
            'scored' => 40,
            'unscored' => 60,
        ]);
        $this->articleRepository->method('getSentimentDistribution')->willReturn([
            'average' => 0.15,
            'positive' => 20,
            'neutral' => 15,
            'negative' => 5,
        ]);

        $this->queueDepthService->method('getEnrichQueueDepth')->willReturn(3);
        $this->modelQualityTracker->method('getStatsByCategory')
            ->willReturn(new ModelQualityStatsMap([]));

        $expectedResponse = new Response('ok');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('settings/pipelines.html.twig', self::callback(static function (array $params): bool {
                /** @var array{total: int, enabled: int} $feedStats */
                $feedStats = $params['feedStats'];
                /** @var array{successRate: float} $fullTextStats */
                $fullTextStats = $params['fullTextStats'];

                return isset($params['feedStats'], $params['sources'], $params['fullTextStats'], $params['enrichmentStats'], $params['enrichQueueDepth'], $params['modelStats'], $params['embeddingStats'], $params['sentimentStats'], $params['sentimentDistribution'])
                    && $feedStats['total'] === 5
                    && $feedStats['enabled'] === 4
                    && $fullTextStats['successRate'] === 94.1
                    && $params['enrichQueueDepth'] === 3;
            }))
            ->willReturn($expectedResponse);

        $response = ($this->controller)();

        self::assertSame($expectedResponse, $response);
    }

    public function testFullTextSuccessRateIsZeroWhenNoAttempted(): void
    {
        $this->sourceRepository->method('countByHealth')->willReturn([
            'healthy' => 0,
            'degraded' => 0,
            'failing' => 0,
            'disabled_health' => 0,
        ]);
        $this->sourceRepository->method('countEnabled')->willReturn(0);
        $this->sourceRepository->method('countAll')->willReturn(0);
        $this->sourceRepository->method('findAllOrderedByHealth')->willReturn([]);

        $this->articleRepository->method('getFullTextStats')->willReturn([
            'total' => 0,
            'fetched' => 0,
            'failed' => 0,
            'pending' => 0,
            'skipped' => 0,
            'no_status' => 0,
        ]);
        $this->articleRepository->method('getEnrichmentStats')->willReturn([
            'total' => 0,
            'ai' => 0,
            'rule_based' => 0,
            'pending' => 0,
            'complete' => 0,
            'no_method' => 0,
        ]);
        $this->articleRepository->method('getEmbeddingStats')->willReturn([
            'total' => 0,
            'with_embedding' => 0,
            'without_embedding' => 0,
        ]);
        $this->articleRepository->method('getSentimentStats')->willReturn([
            'total' => 0,
            'scored' => 0,
            'unscored' => 0,
        ]);
        $this->articleRepository->method('getSentimentDistribution')->willReturn([
            'average' => 0.0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
        ]);

        $this->queueDepthService->method('getEnrichQueueDepth')->willReturn(0);
        $this->modelQualityTracker->method('getStatsByCategory')
            ->willReturn(new ModelQualityStatsMap([]));

        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('settings/pipelines.html.twig', self::callback(static function (array $params): bool {
                /** @var array{successRate: float} $fullTextStats */
                $fullTextStats = $params['fullTextStats'];

                return $fullTextStats['successRate'] === 0.0;
            }))
            ->willReturn(new Response('ok'));

        ($this->controller)();
    }
}
