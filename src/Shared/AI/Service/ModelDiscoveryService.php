<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelDiscoveryService implements ModelDiscoveryServiceInterface
{
    private const string CACHE_KEY = 'openrouter_free_models';

    private const int CACHE_TTL = 3600; // 1 hour

    private const string CIRCUIT_BREAKER_KEY = 'openrouter_circuit_breaker';

    private const int CIRCUIT_BREAKER_THRESHOLD = 3;

    private const int CIRCUIT_BREAKER_RESET_SECONDS = 86400; // 24 hours

    private const int MIN_CONTEXT_LENGTH = 8192;

    private int $failureCount = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $blockedModels = '',
    ) {
    }

    public function discoverFreeModels(): ModelIdCollection
    {
        // Check circuit breaker
        $breakerItem = $this->cache->getItem(self::CIRCUIT_BREAKER_KEY);
        if ($breakerItem->isHit()) {
            $this->logger->debug('Circuit breaker open, using cached model list');

            return $this->getCachedModels();
        }

        // Check cache
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            /** @var list<string> $cached */
            $cached = $cacheItem->get();

            return $this->toCollection($cached);
        }

        // Fetch from API
        try {
            $models = $this->fetchFreeModels();
            $this->failureCount = 0;

            // Cache raw strings (serializable)
            $rawIds = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
            $cacheItem->set($rawIds);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            $this->logger->info('Discovered {count} free OpenRouter models', [
                'count' => $models->count(),
            ]);

            return $models;
        } catch (\Throwable $e) {
            $this->failureCount++;
            $this->logger->warning('Model discovery failed ({count}/{threshold}): {error}', [
                'count' => $this->failureCount,
                'threshold' => self::CIRCUIT_BREAKER_THRESHOLD,
                'error' => $e->getMessage(),
            ]);

            if ($this->failureCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
                $this->openCircuitBreaker();
            }

            return $this->getCachedModels();
        }
    }

    private function fetchFreeModels(): ModelIdCollection
    {
        $response = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/models');
        $data = $response->toArray();

        $blockedList = $this->blockedModels !== ''
            ? array_map('trim', explode(',', $this->blockedModels))
            : [];

        $freeModels = [];
        /** @var list<array{id: string, context_length: int, pricing: array{prompt: string, completion: string}}> $models */
        $models = $data['data'] ?? [];
        foreach ($models as $model) {
            $isFree = $model['pricing']['prompt'] === '0'
                && $model['pricing']['completion'] === '0';

            if (! $isFree) {
                continue;
            }

            if ($model['context_length'] < self::MIN_CONTEXT_LENGTH) {
                continue;
            }

            if (in_array($model['id'], $blockedList, true)) {
                continue;
            }

            $freeModels[] = new ModelId($model['id']);
        }

        return new ModelIdCollection($freeModels);
    }

    private function getCachedModels(): ModelIdCollection
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            /** @var list<string> $cached */
            $cached = $item->get();

            return $this->toCollection($cached);
        }

        // No cache, return empty — callers should fall back to openrouter/free
        return new ModelIdCollection();
    }

    /**
     * @param list<string> $ids
     */
    private function toCollection(array $ids): ModelIdCollection
    {
        return new ModelIdCollection(array_map(static fn (string $id): ModelId => new ModelId($id), $ids));
    }

    private function openCircuitBreaker(): void
    {
        $item = $this->cache->getItem(self::CIRCUIT_BREAKER_KEY);
        $item->set(true);
        $item->expiresAfter(self::CIRCUIT_BREAKER_RESET_SECONDS);
        $this->cache->save($item);

        $this->logger->warning('Circuit breaker opened for model discovery ({seconds}s)', [
            'seconds' => self::CIRCUIT_BREAKER_RESET_SECONDS,
        ]);
    }
}
