<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

use App\Shared\Service\QueueDepthServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * A PlatformInterface decorator that tries multiple models in sequence
 * on a single underlying platform. If the requested model fails, it
 * falls through a configured list of fallback models.
 *
 * Queue-aware routing: when the enrichment queue is deep, free models
 * are skipped partially or entirely to accelerate processing via paid models.
 *
 * This complements Symfony's FailoverPlatform (which chains platforms,
 * not models) for the case where one provider offers multiple free models.
 */
final class ModelFailoverPlatform implements PlatformInterface
{
    /**
     * @var list<string>
     */
    private readonly array $fallbackModels;

    /**
     * @param list<string> $fallbackModels Models to try after the requested model fails
     * @param string $paidFallbackModel Optional paid model appended after free models (from OPENROUTER_PAID_FALLBACK_MODEL env var)
     * @param int $queueAccelerateThreshold Above this depth, try primary free model only then skip to paid
     * @param int $queueSkipFreeThreshold Above this depth, skip free models entirely and use paid directly
     */
    public function __construct(
        private readonly PlatformInterface $innerPlatform,
        array $fallbackModels = [],
        private readonly string $paidFallbackModel = '',
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?QueueDepthServiceInterface $queueDepthService = null,
        private readonly int $queueAccelerateThreshold = 20,
        private readonly int $queueSkipFreeThreshold = 50,
    ) {
        if ($this->paidFallbackModel !== '') {
            $fallbackModels[] = $this->paidFallbackModel;
        }
        $this->fallbackModels = $fallbackModels;
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        $modelsToTry = $this->resolveModelChain($model);
        $lastException = new \RuntimeException('All models in failover chain exhausted');

        foreach ($modelsToTry as $candidateModel) {
            try {
                $result = $this->innerPlatform->invoke($candidateModel, $input, $options);
                // Force eager evaluation — DeferredResult throws on asText(), not invoke()
                $result->asText();

                // Capture the actual model used (OpenRouter resolves openrouter/free to a real model)
                $actualModel = $result->getRawResult()->getData()['model'] ?? $candidateModel;
                $result->getMetadata()->add('actual_model', $actualModel);

                return $result;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logger->info('Model {model} failed, trying next: {error}', [
                    'model' => $candidateModel,
                    'error' => $e->getMessage(),
                ]);

                // Rate limiting affects the entire provider — don't waste time trying other models
                if ($e instanceof RateLimitExceededException) {
                    break;
                }
            }
        }

        throw $lastException;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->innerPlatform->getModelCatalog();
    }

    /**
     * Build the model chain based on queue depth.
     *
     * - queue < accelerateThreshold: normal (primary + all fallbacks + paid)
     * - queue >= accelerateThreshold and < skipFreeThreshold: primary free only, then paid
     * - queue >= skipFreeThreshold: paid model only
     *
     * @param non-empty-string $primaryModel
     *
     * @return list<non-empty-string>
     */
    private function resolveModelChain(string $primaryModel): array
    {
        /** @var list<non-empty-string> $fullChain */
        $fullChain = [$primaryModel, ...$this->fallbackModels];

        if ($this->paidFallbackModel === '' || ! $this->queueDepthService instanceof QueueDepthServiceInterface) {
            return $fullChain;
        }

        $queueDepth = $this->queueDepthService->getEnrichQueueDepth();

        if ($queueDepth >= $this->queueSkipFreeThreshold) {
            $this->logger->info('Queue depth {depth} >= {threshold}, skipping free models — using paid model directly', [
                'depth' => $queueDepth,
                'threshold' => $this->queueSkipFreeThreshold,
            ]);

            return [$this->paidFallbackModel];
        }

        if ($queueDepth >= $this->queueAccelerateThreshold) {
            $this->logger->info('Queue depth {depth} >= {threshold}, accelerating — primary free then paid', [
                'depth' => $queueDepth,
                'threshold' => $this->queueAccelerateThreshold,
            ]);

            return [$primaryModel, $this->paidFallbackModel];
        }

        return $fullChain;
    }
}
