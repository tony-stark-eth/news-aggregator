<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * A PlatformInterface decorator that tries multiple models in sequence
 * on a single underlying platform. If the requested model fails, it
 * falls through a configured list of fallback models.
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
     */
    public function __construct(
        private readonly PlatformInterface $innerPlatform,
        array $fallbackModels = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->fallbackModels = $fallbackModels;
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        /** @var list<non-empty-string> $modelsToTry */
        $modelsToTry = [$model, ...$this->fallbackModels];
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
                if (str_contains($e->getMessage(), 'Rate limit')) {
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
}
