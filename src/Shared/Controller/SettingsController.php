<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly string $openrouterApiKey,
        private readonly string $notifierDsn,
        private readonly int $retentionArticles,
        private readonly int $retentionLogs,
    ) {
    }

    #[Route('/settings', name: 'app_settings')]
    public function __invoke(): Response
    {
        return $this->render('settings/index.html.twig', [
            'hasOpenrouterKey' => $this->openrouterApiKey !== '',
            'hasNotifierDsn' => $this->notifierDsn !== '' && $this->notifierDsn !== 'null://null',
            'retentionArticles' => $this->retentionArticles,
            'retentionLogs' => $this->retentionLogs,
        ]);
    }
}
