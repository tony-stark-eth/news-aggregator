<?php

declare(strict_types=1);

use App\Article\MessageHandler\FetchSourceHandler;
use App\Article\Service\AiDeduplicationService;
use App\Article\Service\DeduplicationService;
use App\Article\Service\DeduplicationServiceInterface;
use App\Digest\Service\DigestSummaryService;
use App\Enrichment\Service\AiCategorizationService;
use App\Enrichment\Service\AiKeywordExtractionService;
use App\Enrichment\Service\AiSummarizationService;
use App\Enrichment\Service\AiTranslationService;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Notification\Command\LoadAlertRulesCommand;
use App\Notification\Service\AiAlertEvaluationService;
use App\Shared\AI\Command\AiSmokeTestCommand;
use App\Shared\AI\Platform\ModelFailoverPlatform;
use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\Command\CleanupCommand;
use App\Shared\Controller\SettingsController;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use App\Shared\Search\Service\SealArticleSearchService;
use App\Source\Command\SeedDataCommand;
use App\Source\Scheduler\FetchScheduleProvider;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Capability;
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
        CategorizationServiceInterface::class,
        AiCategorizationService::class,
    );

    $services->alias(
        SummarizationServiceInterface::class,
        AiSummarizationService::class,
    );

    $services->alias(
        DeduplicationServiceInterface::class,
        AiDeduplicationService::class,
    );

    $services->alias(
        TranslationServiceInterface::class,
        AiTranslationService::class,
    );

    $services->alias(
        KeywordExtractionServiceInterface::class,
        AiKeywordExtractionService::class,
    );

    // Register openrouter/free router in the model catalog (not included by default)
    $services->set('ai.platform.model_catalog.openrouter', ModelCatalog::class)
        ->arg('$additionalModels', [
            'openrouter/free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
        ]);

    // Model failover platform: openrouter/free → specific :free models → exception
    // Wraps the OpenRouter platform with model-level failover (complements FailoverPlatform's platform-level failover)
    $services->set('ai.platform.openrouter.failover', ModelFailoverPlatform::class)
        ->arg('$innerPlatform', service('ai.platform.openrouter'))
        ->arg('$fallbackModels', [
            'minimax/minimax-m2.5:free',
            'z-ai/glm-4.5-air:free',
            'openai/gpt-oss-120b:free',
            'qwen/qwen3.6-plus:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
        ]);

    // All AI services use the failover-wrapped platform
    $services->set(AiCategorizationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiSummarizationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiTranslationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiKeywordExtractionService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiDeduplicationService::class)
        ->arg('$ruleBasedFallback', service(DeduplicationService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiAlertEvaluationService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(DigestSummaryService::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiSmokeTestCommand::class)
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    // Wire admin email for LoadAlertRulesCommand
    $services->set(LoadAlertRulesCommand::class)
        ->arg('$adminEmail', '%env(ADMIN_EMAIL)%');

    // Wire admin credentials for SeedDataCommand
    $services->set(SeedDataCommand::class)
        ->arg('$adminEmail', '%env(ADMIN_EMAIL)%')
        ->arg('$adminPassword', '%env(default:seed_password:ADMIN_PASSWORD)%');

    $container->parameters()->set('seed_password', 'demo');

    // Wire OPENROUTER_BLOCKED_MODELS env var for ModelDiscoveryService
    $services->set(ModelDiscoveryService::class)
        ->arg('$blockedModels', '%env(string:OPENROUTER_BLOCKED_MODELS)%');

    // Wire retention env vars for CleanupCommand
    $services->set(CleanupCommand::class)
        ->arg('$retentionArticleDays', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogDays', '%env(int:RETENTION_LOGS)%');

    // Wire default fetch interval for FetchScheduleProvider
    $services->set(FetchScheduleProvider::class)
        ->arg('$defaultIntervalMinutes', '%env(int:FETCH_DEFAULT_INTERVAL_MINUTES)%');

    // Wire env vars for SettingsController
    $services->set(SettingsController::class)
        ->arg('$openrouterApiKey', '%env(default::OPENROUTER_API_KEY)%')
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%')
        ->arg('$retentionArticles', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogs', '%env(int:RETENTION_LOGS)%');

    // Wire DISPLAY_LANGUAGES env var for FetchSourceHandler
    $services->set(FetchSourceHandler::class)
        ->arg('$displayLanguages', '%env(string:DISPLAY_LANGUAGES)%');

    // Search: SEAL/Loupe engine wired by argument name (loupeEngine → cmsig_seal.engine.loupe alias)
    $services->alias(ArticleSearchServiceInterface::class, SealArticleSearchService::class);
};
