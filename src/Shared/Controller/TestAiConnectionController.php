<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\AI\Service\AiConnectionTestServiceInterface;
use App\Shared\Service\SettingsServiceInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TestAiConnectionController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly AiConnectionTestServiceInterface $connectionTestService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/settings/test-ai-connection', name: 'app_settings_test_ai_connection', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('test_ai_connection', $token)) {
            return $this->respond($isHtmx, 'error', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $credentials = $this->resolveCredentials($request);
        if ($credentials['error'] !== null) {
            return $this->respond($isHtmx, 'warning', $credentials['error']);
        }

        return $this->testConnection($isHtmx, $credentials['baseUrl'], $credentials['model'], $credentials['apiKey']);
    }

    /**
     * @return array{baseUrl: string, model: string, apiKey: string, error: ?string}
     */
    private function resolveCredentials(Request $request): array
    {
        $baseUrl = trim($request->request->getString('ai_openai_base_url'));
        $model = trim($request->request->getString('ai_openai_model'));
        $apiKey = $request->request->getString('ai_openai_api_key');

        if ($baseUrl === '') {
            $baseUrl = $this->settingsService->getOpenAiBaseUrl();
        }

        if ($model === '') {
            $model = $this->settingsService->getOpenAiModel();
        }

        if ($apiKey === '') {
            $apiKey = $this->settingsService->getOpenAiApiKey();
        }

        if ($baseUrl === '' || $model === '') {
            return ['baseUrl' => '', 'model' => '', 'apiKey' => '', 'error' => 'Base URL and model are required.'];
        }

        if ($apiKey === '') {
            return ['baseUrl' => '', 'model' => '', 'apiKey' => '', 'error' => 'API key is required.'];
        }

        return ['baseUrl' => $baseUrl, 'model' => $model, 'apiKey' => $apiKey, 'error' => null];
    }

    private function testConnection(bool $isHtmx, string $baseUrl, string $model, string $apiKey): Response
    {
        try {
            $responseText = $this->connectionTestService->testOpenAiCompatible($baseUrl, $model, $apiKey);
        } catch (\InvalidArgumentException $e) {
            return $this->respond($isHtmx, 'warning', $e->getMessage());
        } catch (\Throwable) {
            return $this->respond($isHtmx, 'error', 'Connection failed');
        }

        $preview = mb_strlen($responseText) > 80
            ? mb_substr($responseText, 0, 77) . '...'
            : $responseText;

        return $this->respond($isHtmx, 'success', sprintf('Connected — model replied: "%s"', $preview));
    }

    private function respond(bool $isHtmx, string $level, string $message, int $statusCode = Response::HTTP_OK): Response
    {
        if ($isHtmx) {
            $badgeClass = match ($level) {
                'success' => 'badge-success',
                'warning' => 'badge-warning',
                default => 'badge-error',
            };

            return new Response(
                sprintf('<span class="badge %s badge-sm">%s</span>', $badgeClass, htmlspecialchars($message, ENT_QUOTES)),
                $statusCode,
            );
        }

        $this->controller->addFlash($level, $message);

        return new RedirectResponse($this->urlGenerator->generate('app_settings'));
    }
}
