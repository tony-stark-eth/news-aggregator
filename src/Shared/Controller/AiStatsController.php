<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiStatsController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ModelQualityTrackerInterface $qualityTracker,
        private readonly ModelDiscoveryServiceInterface $modelDiscovery,
    ) {
    }

    #[Route('/stats/ai', name: 'app_ai_stats')]
    public function __invoke(): Response
    {
        return $this->controller->render('stats/ai.html.twig', [
            'stats' => $this->qualityTracker->getAllStats(),
            'freeModels' => $this->modelDiscovery->discoverFreeModels(),
        ]);
    }
}
