<?php

declare(strict_types=1);

use App\Article\Mercure\MercurePublisherService;
use App\Article\Mercure\MercurePublisherServiceInterface;
use App\Article\Service\AiDeduplicationService;
use App\Article\Service\DeduplicationService;
use App\Article\Service\DeduplicationServiceInterface;
use App\Chat\Service\ArticleChatService;
use App\Chat\Service\ArticleChatServiceInterface;
use App\Chat\Store\ConversationMessageStore;
use App\Chat\Store\ConversationMessageStoreInterface;
use App\Chat\Tool\ArticleSearchTool;
use App\Digest\Service\DigestSummaryService;
use App\Enrichment\Service\AiBatchTranslationService;
use App\Enrichment\Service\AiCategorizationService;
use App\Enrichment\Service\AiCombinedEnrichmentService;
use App\Enrichment\Service\AiKeywordExtractionService;
use App\Enrichment\Service\AiSummarizationService;
use App\Enrichment\Service\AiTranslationService;
use App\Enrichment\Service\ArticleTranslationService;
use App\Enrichment\Service\ArticleTranslationServiceInterface;
use App\Enrichment\Service\BatchTranslationServiceInterface;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\CombinedEnrichmentServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\RuleBasedCategorizationService;
use App\Enrichment\Service\RuleBasedEnrichmentService;
use App\Enrichment\Service\RuleBasedKeywordExtractionService;
use App\Enrichment\Service\RuleBasedSummarizationService;
use App\Enrichment\Service\RuleBasedTranslationService;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Notification\Command\LoadAlertRulesCommand;
use App\Notification\Service\AiAlertEvaluationService;
use App\Notification\Service\NotificationDispatchService;
use App\Shared\AI\Command\AiSmokeTestCommand;
use App\Shared\AI\Platform\ModelFailoverPlatform;
use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\Controller\AiStatsController;
use App\Shared\Controller\SettingsController;
use App\Shared\Controller\TestNotificationController;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use App\Shared\Search\Service\SealArticleSearchService;
use App\Shared\Service\QueueDepthServiceInterface;
use App\Shared\Service\SettingsService;
use App\Shared\Service\SettingsServiceInterface;
use App\Source\Command\SeedDataCommand;
use Symfony\AI\Agent\Toolbox\Toolbox;
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

    $services->alias(
        CombinedEnrichmentServiceInterface::class,
        AiCombinedEnrichmentService::class,
    );

    $services->alias(
        BatchTranslationServiceInterface::class,
        AiBatchTranslationService::class,
    );

    $services->alias(
        ArticleTranslationServiceInterface::class,
        ArticleTranslationService::class,
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

    // Model failover platform: openrouter/free → specific :free models → optional paid fallback → exception
    // Wraps the OpenRouter platform with model-level failover (complements FailoverPlatform's platform-level failover)
    $services->set('ai.platform.openrouter.failover', ModelFailoverPlatform::class)
        ->arg('$innerPlatform', service('ai.platform.openrouter'))
        ->arg('$fallbackModels', [
            'minimax/minimax-m2.5:free',
            'z-ai/glm-4.5-air:free',
            'openai/gpt-oss-120b:free',
            'qwen/qwen3.6-plus:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
        ])
        ->arg('$paidFallbackModel', '%env(string:OPENROUTER_PAID_FALLBACK_MODEL)%')
        ->arg('$queueDepthService', service(QueueDepthServiceInterface::class))
        ->arg('$queueAccelerateThreshold', '%env(int:QUEUE_ACCELERATE_THRESHOLD)%')
        ->arg('$queueSkipFreeThreshold', '%env(int:QUEUE_SKIP_FREE_THRESHOLD)%');

    // All AI services use the failover-wrapped platform
    $services->set(AiCategorizationService::class)
        ->arg('$ruleBasedFallback', service(RuleBasedCategorizationService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiSummarizationService::class)
        ->arg('$ruleBasedFallback', service(RuleBasedSummarizationService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiTranslationService::class)
        ->arg('$ruleBasedFallback', service(RuleBasedTranslationService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiKeywordExtractionService::class)
        ->arg('$ruleBasedFallback', service(RuleBasedKeywordExtractionService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiCombinedEnrichmentService::class)
        ->arg('$categorizationFallback', service(AiCategorizationService::class))
        ->arg('$summarizationFallback', service(AiSummarizationService::class))
        ->arg('$keywordExtractionFallback', service(AiKeywordExtractionService::class))
        ->arg('$platform', service('ai.platform.openrouter.failover'));

    $services->set(AiBatchTranslationService::class)
        ->arg('$translationFallback', service(AiTranslationService::class))
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

    // Wire AI stats controller with blocked models and paid fallback model
    $services->set(AiStatsController::class)
        ->arg('$blockedModels', '%env(string:OPENROUTER_BLOCKED_MODELS)%')
        ->arg('$paidFallbackModel', '%env(string:OPENROUTER_PAID_FALLBACK_MODEL)%');

    // SettingsService: hybrid env-var defaults + DB overrides
    $services->set(SettingsService::class)
        ->arg('$displayLanguages', '%env(string:DISPLAY_LANGUAGES)%')
        ->arg('$fetchDefaultInterval', '%env(int:FETCH_DEFAULT_INTERVAL_MINUTES)%')
        ->arg('$retentionArticles', '%env(int:RETENTION_ARTICLES)%')
        ->arg('$retentionLogs', '%env(int:RETENTION_LOGS)%');

    $services->alias(SettingsServiceInterface::class, SettingsService::class);

    // Wire env vars for SettingsController
    $services->set(SettingsController::class)
        ->arg('$openrouterApiKey', '%env(default::OPENROUTER_API_KEY)%')
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%');

    // Wire notifier DSN for TestNotificationController (to detect null transport)
    $services->set(TestNotificationController::class)
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%');

    // Wire notifier DSN for NotificationDispatchService (to detect null transport)
    $services->set(NotificationDispatchService::class)
        ->arg('$notifierDsn', '%env(default::NOTIFIER_CHATTER_DSN)%');

    // Mercure publisher: real implementation when Hub is available
    $services->alias(MercurePublisherServiceInterface::class, MercurePublisherService::class);

    // RuleBasedEnrichmentService: explicitly bind rule-based implementations (not AI decorators)
    $services->set(RuleBasedEnrichmentService::class)
        ->arg('$categorization', service(RuleBasedCategorizationService::class))
        ->arg('$summarization', service(RuleBasedSummarizationService::class))
        ->arg('$keywordExtraction', service(RuleBasedKeywordExtractionService::class));

    // Search: SEAL/Loupe engine wired by argument name (loupeEngine → cmsig_seal.engine.loupe alias)
    $services->alias(ArticleSearchServiceInterface::class, SealArticleSearchService::class);

    // Chat: Toolbox wrapping the ArticleSearchTool for the agent
    $services->set('chat.toolbox', Toolbox::class)
        ->arg('$tools', [service(ArticleSearchTool::class)]);

    // Chat: ArticleChatService uses inner OpenRouter platform for tool-calling agent
    $services->set(ArticleChatService::class)
        ->arg('$innerPlatform', service('ai.platform.openrouter'))
        ->arg('$toolbox', service('chat.toolbox'));

    $services->alias(ArticleChatServiceInterface::class, ArticleChatService::class);

    // Chat: ConversationMessageStore uses DBAL connection
    $services->set(ConversationMessageStore::class)
        ->arg('$connection', service('doctrine.dbal.default_connection'));

    $services->alias(ConversationMessageStoreInterface::class, ConversationMessageStore::class);
};
