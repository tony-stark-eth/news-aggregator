<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\ValueObject\ModelId;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class EmbeddingService implements EmbeddingServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ModelDiscoveryServiceInterface $modelDiscovery,
        private LoggerInterface $logger,
    ) {
    }

    public function embed(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $models = $this->modelDiscovery->discoverEmbeddingModels();
        if ($models->isEmpty()) {
            $this->logger->warning('No embedding models available');

            return null;
        }

        foreach ($models as $model) {
            $result = $this->tryEmbed($trimmed, $model);
            if ($result !== null) {
                return $result;
            }
        }

        $this->logger->warning('All embedding models failed', [
            'models_tried' => $models->count(),
        ]);

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function tryEmbed(string $text, ModelId $model): ?array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/embeddings', [
                'json' => [
                    'model' => $model->value,
                    'input' => $text,
                ],
            ]);

            /** @var array{data?: list<array{embedding?: list<float>}>} $data */
            $data = $response->toArray();

            $embedding = $data['data'][0]['embedding'] ?? null;
            if (! \is_array($embedding) || $embedding === []) {
                $this->logger->warning('Empty embedding response from {model}', [
                    'model' => $model->value,
                ]);

                return null;
            }

            $this->logger->debug('Generated embedding via {model} ({dimensions}d)', [
                'model' => $model->value,
                'dimensions' => \count($embedding),
            ]);

            return $embedding;
        } catch (\Throwable $e) {
            $this->logger->warning('Embedding request failed for {model}: {error}', [
                'model' => $model->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
