<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Service\SettingsService;
use App\Shared\Service\SettingsServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SentimentApiController
{
    public function __construct(
        private SettingsServiceInterface $settingsService,
    ) {
    }

    #[Route('/api/settings/sentiment', name: 'api_settings_sentiment', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $value = $request->request->getInt('value');

        if ($value < -10 || $value > 10) {
            return new JsonResponse([
                'error' => 'Value must be between -10 and +10',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->settingsService->set(SettingsService::KEY_SENTIMENT_SLIDER, (string) $value);

        return new JsonResponse([
            'value' => $value,
        ]);
    }
}
