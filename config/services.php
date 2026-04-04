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

    // Wire OpenRouter platform for AI services
    $services->set(\App\Enrichment\Service\AiCategorizationService::class)
        ->arg('$platform', service('ai.platform.openrouter'));

    $services->set(\App\Enrichment\Service\AiSummarizationService::class)
        ->arg('$platform', service('ai.platform.openrouter'));

    $services->set(\App\Article\Service\AiDeduplicationService::class)
        ->arg('$ruleBasedFallback', service(\App\Article\Service\DeduplicationService::class))
        ->arg('$platform', service('ai.platform.openrouter'));

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

    // Wire OpenRouter platform for AI alert evaluation
    $services->set(\App\Notification\Service\AiAlertEvaluationService::class)
        ->arg('$platform', service('ai.platform.openrouter'));

    // Wire OpenRouter platform for digest summary generation
    $services->set(\App\Digest\Service\DigestSummaryService::class)
        ->arg('$platform', service('ai.platform.openrouter'));

    // Wire OpenRouter platform for smoke test command
    $services->set(\App\Shared\AI\Command\AiSmokeTestCommand::class)
        ->arg('$platform', service('ai.platform.openrouter'));

    // Wire env vars for SettingsController
    $services->set(\App\Shared\Controller\SettingsController::class)
        ->arg('$openrouterApiKey', '%env(default::OPENROUTER_API_KEY)%')
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%')
        ->arg('$retentionArticles', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogs', '%env(int:RETENTION_LOGS)%');
};
