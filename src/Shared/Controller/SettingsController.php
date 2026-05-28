<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Service\SettingsService;
use App\Shared\Service\SettingsServiceInterface;
use App\Shared\ValueObject\AiProvider;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController
{
    /**
     * @var list<string>
     */
    private const array AI_SETTING_KEYS = [
        SettingsService::KEY_AI_PROVIDER,
        SettingsService::KEY_AI_OPENAI_BASE_URL,
        SettingsService::KEY_AI_OPENAI_MODEL,
        SettingsService::KEY_AI_OPENAI_API_KEY,
    ];

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
            'aiConfigured' => $this->settingsService->isAiConfigured($this->openrouterApiKey),
            'openAiApiKeyConfigured' => $this->settingsService->hasOpenAiApiKey(),
            'hasNotifierDsn' => $this->notifierDsn !== '' && $this->notifierDsn !== 'null://null',
            'settings' => $this->settingsService->getAll(),
        ]);
    }

    #[Route('/settings/save', name: 'app_settings_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $token = $request->request->getString('_csrf_token');

        if (! $this->controller->isCsrfTokenValid('settings_save', $token)) {
            return $this->saveErrorResponse($request, 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $aiError = $this->saveAiProviderSettings($request);
        if ($aiError !== null) {
            return $this->saveErrorResponse($request, $aiError, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->saveGeneralSettings($request);

        return $this->saveSuccessResponse($request);
    }

    private function saveGeneralSettings(Request $request): void
    {
        $allSettings = $this->settingsService->getAll();

        foreach (array_keys($allSettings) as $key) {
            if (\in_array($key, self::AI_SETTING_KEYS, true)) {
                continue;
            }

            $value = $request->request->getString($key);

            if ($value !== '') {
                $this->settingsService->set($key, $value);
            }
        }
    }

    private function saveSuccessResponse(Request $request): Response
    {
        if ($this->isHtmxRequest($request)) {
            return new Response(
                $this->buildSaveFeedbackResponse('success', 'Settings saved successfully.'),
            );
        }

        $this->controller->addFlash('success', 'Settings saved successfully.');

        return $this->controller->redirectToRoute('app_settings');
    }

    private function saveErrorResponse(Request $request, string $message, int $statusCode): Response
    {
        if ($this->isHtmxRequest($request)) {
            return new Response(
                $this->buildSaveFeedbackResponse('error', $message),
                $statusCode,
            );
        }

        return new Response(
            sprintf('<span class="text-error">%s</span>', htmlspecialchars($message, ENT_QUOTES)),
            $statusCode,
        );
    }

    private function isHtmxRequest(Request $request): bool
    {
        return $request->headers->has('HX-Request');
    }

    private function buildSaveFeedbackResponse(string $level, string $message): string
    {
        $alertClass = $level === 'success' ? 'alert-success' : 'alert-error';
        $badgeClass = $level === 'success' ? 'badge-success' : 'badge-error';
        $badgeLabel = $level === 'success' ? 'Saved' : 'Save failed';
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES);

        return sprintf(
            '<div id="settings-save-feedback" hx-swap-oob="innerHTML" class="alert %s shadow-sm max-w-lg"><span>%s</span></div>'
            . '<span class="badge %s badge-sm">%s</span>',
            $alertClass,
            $escapedMessage,
            $badgeClass,
            $badgeLabel,
        );
    }

    private function saveAiProviderSettings(Request $request): ?string
    {
        $providerValue = $request->request->getString('ai_provider');
        $provider = AiProvider::fromString($providerValue);
        $this->settingsService->set(SettingsService::KEY_AI_PROVIDER, $provider->value);

        if ($provider !== AiProvider::OpenAi) {
            return null;
        }

        $baseUrl = trim($request->request->getString('ai_openai_base_url'));
        $model = trim($request->request->getString('ai_openai_model'));
        $apiKey = $request->request->getString('ai_openai_api_key');

        if ($baseUrl === '') {
            $baseUrl = $this->settingsService->getOpenAiBaseUrl();
        }

        if ($model === '') {
            $model = $this->settingsService->getOpenAiModel();
        }

        if ($baseUrl === '' || $model === '') {
            return 'OpenAI-compatible provider requires Base URL and Model.';
        }

        if ($apiKey === '' && ! $this->settingsService->hasOpenAiApiKey()) {
            return 'OpenAI-compatible provider requires an API key.';
        }

        $this->settingsService->set(SettingsService::KEY_AI_OPENAI_BASE_URL, $baseUrl);
        $this->settingsService->set(SettingsService::KEY_AI_OPENAI_MODEL, $model);

        if ($apiKey !== '') {
            $this->settingsService->set(SettingsService::KEY_AI_OPENAI_API_KEY, $apiKey);
        }

        return null;
    }
}
