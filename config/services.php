<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Default configuration for services in this file
    $services->defaults()
        ->autowire(true)      // Automatically injects dependencies in your services.
        ->autoconfigure(true); // Automatically registers your services as commands, event subscribers, etc.

    // Makes classes in src/ available to be used as services.
    // This creates a service per class whose id is the fully-qualified class name.
    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Entity/',
            '../src/*/Entity/',
            '../src/Kernel.php',
        ]);

    // Add more service definitions when explicit configuration is needed.
    // Note that last definitions always *replace* previous ones.

    // AI implementations as defaults — they fall back to rule-based internally on failure.
    $services->alias(
        \App\Enrichment\Service\CategorizationServiceInterface::class,
        \App\Enrichment\Service\AiCategorizationService::class,
    );

    $services->alias(
        \App\Enrichment\Service\SummarizationServiceInterface::class,
        \App\Enrichment\Service\AiSummarizationService::class,
    );

    $services->alias(
        \App\Article\Service\DeduplicationServiceInterface::class,
        \App\Article\Service\AiDeduplicationService::class,
    );

    // Register openrouter/free router in the model catalog (not included by default)
    $services->set('ai.platform.model_catalog.openrouter', \Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog::class)
        ->arg('$additionalModels', [
            'openrouter/free' => [
                'class' => \Symfony\AI\Platform\Bridge\Generic\CompletionsModel::class,
                'capabilities' => [
                    \Symfony\AI\Platform\Capability::INPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_TEXT,
                    \Symfony\AI\Platform\Capability::OUTPUT_STREAMING,
                ],
            ],
        ]);

    // Model failover platform: openrouter/free → specific :free models → exception
    // Wraps the OpenRouter platform with model-level failover (complements FailoverPlatform's platform-level failover)
    $services->set('ai.platform.openrouter.failover', \App\Shared\AI\Platform\ModelFailoverPlatform::class)
        ->arg('$innerPlatform', service('ai.platform.openrouter'))
        ->arg('$fallbackModels', [
            'minimax/minimax-m2.5:free',
            'z-ai/glm-4.5-air:free',
            'openai/gpt-oss-120b:free',
            'qwen/qwen3.6-plus:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
        ]);

    // All AI services use the failover-wrapped platform
    $services->set(\App\Enrichment\Service\AiCategorizationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(\App\Enrichment\Service\AiSummarizationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(\App\Article\Service\AiDeduplicationService::class)
        ->arg('$ruleBasedFallback', service(\App\Article\Service\DeduplicationService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(\App\Notification\Service\AiAlertEvaluationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(\App\Digest\Service\DigestSummaryService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(\App\Shared\AI\Command\AiSmokeTestCommand::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    // Wire OPENROUTER_BLOCKED_MODELS env var for ModelDiscoveryService
    $services->set(\App\Shared\AI\Service\ModelDiscoveryService::class)
        ->arg('$blockedModels', '%env(string:OPENROUTER_BLOCKED_MODELS)%');

    // Wire retention env vars for CleanupCommand
    $services->set(\App\Shared\Command\CleanupCommand::class)
        ->arg('$retentionArticleDays', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogDays', '%env(int:RETENTION_LOGS)%');

    // Wire default fetch interval for FetchScheduleProvider
    $services->set(\App\Source\Scheduler\FetchScheduleProvider::class)
        ->arg('$defaultIntervalMinutes', '%env(int:FETCH_DEFAULT_INTERVAL_MINUTES)%');

    // Wire env vars for SettingsController
    $services->set(\App\Shared\Controller\SettingsController::class)
        ->arg('$openrouterApiKey', '%env(default::OPENROUTER_API_KEY)%')
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%')
        ->arg('$retentionArticles', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogs', '%env(int:RETENTION_LOGS)%');
};
