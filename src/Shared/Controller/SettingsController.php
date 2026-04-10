<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Service\SettingsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SettingsServiceInterface $settingsService,
        private readonly string $openrouterApiKey,
        private readonly string $notifierDsn,
    ) {
    }

    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->controller->render('settings/index.html.twig', [
            'hasOpenrouterKey' => $this->openrouterApiKey !== '',
            'hasNotifierDsn' => $this->notifierDsn !== '' && $this->notifierDsn !== 'null://null',
            'settings' => $this->settingsService->getAll(),
        ]);
    }

    #[Route('/settings/save', name: 'app_settings_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $token = $request->request->getString('_csrf_token');

        if (! $this->controller->isCsrfTokenValid('settings_save', $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $allSettings = $this->settingsService->getAll();

        foreach (array_keys($allSettings) as $key) {
            $value = $request->request->getString($key);

            if ($value !== '') {
                $this->settingsService->set($key, $value);
            }
        }

        if ($request->headers->has('HX-Request')) {
            return new Response(
                '<span class="text-success">Settings saved.</span>',
            );
        }

        return $this->controller->redirectToRoute('app_settings');
    }
}
