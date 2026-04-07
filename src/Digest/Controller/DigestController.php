<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class DigestController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly DigestLogRepositoryInterface $digestLogRepository,
    ) {
    }

    #[Route('/digests', name: 'app_digests')]
    public function __invoke(
        #[MapQueryParameter]
        ?string $status = null,
    ): Response {
        $configs = $this->digestConfigRepository->findAll();

        $deliverySuccess = match ($status) {
            'success' => true,
            'failed' => false,
            default => null,
        };
        $recentLogs = $this->digestLogRepository->findRecent(20, $deliverySuccess);

        return $this->controller->render('digest/index.html.twig', [
            'configs' => $configs,
            'recentLogs' => $recentLogs,
            'statusFilter' => $status,
        ]);
    }
}
