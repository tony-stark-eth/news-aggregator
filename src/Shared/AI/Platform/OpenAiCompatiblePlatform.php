<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

use App\Shared\Service\SettingsServiceInterface;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * OpenAI-compatible API platform (vLLM, OpenAI, LocalAI, etc.) configured via Settings.
 */
final class OpenAiCompatiblePlatform implements PlatformInterface
{
    private ?Platform $platform = null;

    private string $configHash = '';

    public function __construct(
        private readonly SettingsServiceInterface $settings,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        if (! $this->settings->isOpenAiConfigured()) {
            throw new \RuntimeException('OpenAI-compatible provider is not configured (base URL, API key, and model required).');
        }

        $platform = $this->resolvePlatform();
        $configuredModel = $this->settings->getOpenAiModel();

        if ($configuredModel === '') {
            throw new \RuntimeException('OpenAI-compatible provider model is not configured.');
        }

        return $platform->invoke($configuredModel, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->resolvePlatform()->getModelCatalog();
    }

    private function resolvePlatform(): Platform
    {
        $baseUrl = $this->normalizeBaseUrl($this->settings->getOpenAiBaseUrl());
        $apiKey = $this->settings->getOpenAiApiKey();
        $model = $this->settings->getOpenAiModel();
        $hash = $baseUrl . '|' . $apiKey . '|' . $model;

        if ($this->platform instanceof Platform && $this->configHash === $hash) {
            return $this->platform;
        }

        $catalog = new ModelCatalog([
            $model => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
        ]);

        $this->platform = GenericPlatformFactory::create(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            modelCatalog: $catalog,
            supportsEmbeddings: false,
        );
        $this->configHash = $hash;

        return $this->platform;
    }

    private function normalizeBaseUrl(string $url): string
    {
        return OpenAiCompatibleUrlNormalizer::normalize($url);
    }
}
