<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
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
    public function __invoke(): Response
    {
        $configs = $this->digestConfigRepository->findAll();
        $recentLogs = $this->digestLogRepository->findRecent(20);

        return $this->controller->render('digest/index.html.twig', [
            'configs' => $configs,
            'recentLogs' => $recentLogs,
        ]);
    }
}
