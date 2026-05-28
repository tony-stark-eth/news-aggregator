<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

use App\Shared\Service\SettingsServiceInterface;
use App\Shared\ValueObject\AiProvider;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Routes AI invocations to OpenRouter failover or an OpenAI-compatible endpoint based on Settings.
 */
final readonly class SettingsRoutedPlatform implements PlatformInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private PlatformInterface $openRouterFailover,
        private PlatformInterface $openAiCompatiblePlatform,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        if ($this->settings->getAiProvider() === AiProvider::OpenAi && $this->settings->isOpenAiConfigured()) {
            return $this->openAiCompatiblePlatform->invoke($model, $input, $options);
        }

        return $this->openRouterFailover->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        if ($this->settings->getAiProvider() === AiProvider::OpenAi && $this->settings->isOpenAiConfigured()) {
            return $this->openAiCompatiblePlatform->getModelCatalog();
        }

        return $this->openRouterFailover->getModelCatalog();
    }
}
