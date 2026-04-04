<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\AI\Service\ModelQualityTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiStatsController extends AbstractController
{
    public function __construct(
        private readonly ModelQualityTracker $qualityTracker,
        private readonly ModelDiscoveryService $modelDiscovery,
    ) {
    }

    #[Route('/stats/ai', name: 'app_ai_stats')]
    public function __invoke(): Response
    {
        return $this->render('stats/ai.html.twig', [
            'stats' => $this->qualityTracker->getAllStats(),
            'freeModels' => $this->modelDiscovery->discoverFreeModels(),
        ]);
    }
}
