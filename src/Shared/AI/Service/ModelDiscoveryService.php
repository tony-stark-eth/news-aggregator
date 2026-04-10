<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\CircuitBreakerState;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelDiscoveryService implements ModelDiscoveryServiceInterface
{
    private const string CACHE_KEY_FREE = 'openrouter_free_models';

    private const string CACHE_KEY_TOOL_CALLING = 'openrouter_tool_calling_models';

    private const int CACHE_TTL = 3600;

    private const string BREAKER_KEY_FREE = 'openrouter_cb';

    private const string BREAKER_KEY_TOOL_CALLING = 'openrouter_tool_calling_cb';

    private const int BREAKER_THRESHOLD = 3;

    private const int BREAKER_RESET_SECONDS = 86400;

    private const int MIN_CONTEXT_LENGTH = 8192;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $blockedModels = '',
    ) {
    }

    public function discoverFreeModels(): ModelIdCollection
    {
        return $this->discoverModels(
            self::CACHE_KEY_FREE,
            self::BREAKER_KEY_FREE,
            'free',
            $this->fetchFreeModels(...),
        );
    }

    public function discoverToolCallingModels(): ModelIdCollection
    {
        return $this->discoverModels(
            self::CACHE_KEY_TOOL_CALLING,
            self::BREAKER_KEY_TOOL_CALLING,
            'tool-calling',
            $this->fetchToolCallingModels(...),
        );
    }

    /**
     * @param \Closure(): ModelIdCollection $fetchFn
     */
    private function discoverModels(
        string $cacheKey,
        string $breakerKey,
        string $poolName,
        \Closure $fetchFn,
    ): ModelIdCollection {
        $state = $this->getState($breakerKey);

        if ($state === CircuitBreakerState::Open) {
            $this->logger->debug('Circuit breaker open for {pool} pool, using cached model list', [
                'pool' => $poolName,
            ]);

            return $this->getCachedModels($cacheKey);
        }

        // Closed state: check model cache first
        if ($state === CircuitBreakerState::Closed) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                /** @var list<string> $cached */
                $cached = $cacheItem->get();

                return $this->toCollection($cached);
            }
        }

        // Closed (cache miss) or HalfOpen: probe the API
        if ($state === CircuitBreakerState::HalfOpen) {
            $this->logger->debug('Circuit breaker half-open for {pool} pool, attempting probe request', [
                'pool' => $poolName,
            ]);
        }

        return $this->fetchWithCircuitBreaker(
            $cacheKey,
            $breakerKey,
            $poolName,
            $state,
            $fetchFn,
        );
    }

    /**
     * @param \Closure(): ModelIdCollection $fetchFn
     */
    private function fetchWithCircuitBreaker(
        string $cacheKey,
        string $breakerKey,
        string $poolName,
        CircuitBreakerState $state,
        \Closure $fetchFn,
    ): ModelIdCollection {
        try {
            $models = $fetchFn();

            $this->resetBreaker($breakerKey);
            $this->cacheModels($models, $cacheKey);

            $this->logger->info('Discovered {count} {pool} OpenRouter models', [
                'count' => $models->count(),
                'pool' => $poolName,
            ]);

            return $models;
        } catch (\Throwable $e) {
            return $this->handleFailure($e, $state, $cacheKey, $breakerKey);
        }
    }

    private function handleFailure(
        \Throwable $e,
        CircuitBreakerState $state,
        string $cacheKey,
        string $breakerKey,
    ): ModelIdCollection {
        $failures = $this->incrementFailures($breakerKey);

        $this->logger->warning('Model discovery failed ({count}/{threshold}): {error}', [
            'count' => $failures,
            'threshold' => self::BREAKER_THRESHOLD,
            'error' => $e->getMessage(),
        ]);

        // HalfOpen probe failed or threshold reached: open the breaker
        if ($state === CircuitBreakerState::HalfOpen || $failures >= self::BREAKER_THRESHOLD) {
            $this->openBreaker($breakerKey);
        }

        return $this->getCachedModels($cacheKey);
    }

    /**
     * @return array{state: string, failures: int, opened_at: ?int}
     */
    private function getBreakerData(string $breakerKey): array
    {
        $item = $this->cache->getItem($breakerKey);
        if (! $item->isHit()) {
            return [
                'state' => CircuitBreakerState::Closed->value,
                'failures' => 0,
                'opened_at' => null,
            ];
        }

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $item->get();

        return $data;
    }

    private function getState(string $breakerKey): CircuitBreakerState
    {
        $data = $this->getBreakerData($breakerKey);
        $state = CircuitBreakerState::tryFrom($data['state']) ?? CircuitBreakerState::Closed;

        if ($state !== CircuitBreakerState::Open) {
            return $state;
        }

        // Check if Open state has expired -> transition to HalfOpen
        $openedAt = $data['opened_at'];
        if ($openedAt !== null) {
            $elapsed = $this->clock->now()->getTimestamp() - $openedAt;
            if ($elapsed >= self::BREAKER_RESET_SECONDS) {
                $this->saveBreakerData($breakerKey, CircuitBreakerState::HalfOpen, $data['failures'], $openedAt);

                return CircuitBreakerState::HalfOpen;
            }
        }

        return CircuitBreakerState::Open;
    }

    private function incrementFailures(string $breakerKey): int
    {
        $data = $this->getBreakerData($breakerKey);
        $failures = $data['failures'] + 1;
        $state = CircuitBreakerState::tryFrom($data['state']) ?? CircuitBreakerState::Closed;
        $this->saveBreakerData($breakerKey, $state, $failures, $data['opened_at']);

        return $failures;
    }

    private function openBreaker(string $breakerKey): void
    {
        $this->saveBreakerData(
            $breakerKey,
            CircuitBreakerState::Open,
            self::BREAKER_THRESHOLD,
            $this->clock->now()->getTimestamp(),
        );

        $this->logger->warning('Circuit breaker opened for model discovery ({seconds}s)', [
            'seconds' => self::BREAKER_RESET_SECONDS,
        ]);
    }

    private function resetBreaker(string $breakerKey): void
    {
        $this->cache->deleteItem($breakerKey);
    }

    private function saveBreakerData(
        string $breakerKey,
        CircuitBreakerState $state,
        int $failures,
        ?int $openedAt,
    ): void {
        $item = $this->cache->getItem($breakerKey);
        $item->set([
            'state' => $state->value,
            'failures' => $failures,
            'opened_at' => $openedAt,
        ]);
        // Long TTL — state transitions managed in code, not by cache expiry
        $item->expiresAfter(self::BREAKER_RESET_SECONDS * 2);
        $this->cache->save($item);
    }

    private function cacheModels(ModelIdCollection $models, string $cacheKey): void
    {
        $rawIds = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($rawIds);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);
    }

    /**
     * @return array{models: list<array{id: string, context_length: int, pricing: array{prompt: string, completion: string}, supported_parameters?: list<string>}>, blockedList: list<string>}
     */
    private function fetchModelData(): array
    {
        $response = $this->httpClient->request('GET', 'https://openrouter.ai/api/v1/models');
        $data = $response->toArray();

        $blockedList = $this->blockedModels !== ''
            ? array_map('trim', explode(',', $this->blockedModels))
            : [];

        /** @var list<array{id: string, context_length: int, pricing: array{prompt: string, completion: string}, supported_parameters?: list<string>}> $models */
        $models = $data['data'] ?? [];

        return [
            'models' => $models,
            'blockedList' => $blockedList,
        ];
    }

    private function fetchFreeModels(): ModelIdCollection
    {
        ['models' => $models, 'blockedList' => $blockedList] = $this->fetchModelData();

        $freeModels = [];
        foreach ($models as $model) {
            if (! $this->isFreeModel($model)) {
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

    private function fetchToolCallingModels(): ModelIdCollection
    {
        ['models' => $models, 'blockedList' => $blockedList] = $this->fetchModelData();

        $toolModels = [];
        foreach ($models as $model) {
            if (! $this->isFreeModel($model)) {
                continue;
            }

            if ($model['context_length'] < self::MIN_CONTEXT_LENGTH) {
                continue;
            }

            if (in_array($model['id'], $blockedList, true)) {
                continue;
            }

            $supportedParams = $model['supported_parameters'] ?? [];
            if (! in_array('tools', $supportedParams, true)) {
                continue;
            }

            $toolModels[] = new ModelId($model['id']);
        }

        return new ModelIdCollection($toolModels);
    }

    /**
     * @param array{pricing: array{prompt: string, completion: string}} $model
     */
    private function isFreeModel(array $model): bool
    {
        return $model['pricing']['prompt'] === '0'
            && $model['pricing']['completion'] === '0';
    }

    private function getCachedModels(string $cacheKey): ModelIdCollection
    {
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            /** @var list<string> $cached */
            $cached = $item->get();

            return $this->toCollection($cached);
        }

        return new ModelIdCollection();
    }

    /**
     * @param list<string> $ids
     */
    private function toCollection(array $ids): ModelIdCollection
    {
        return new ModelIdCollection(array_map(static fn (string $id): ModelId => new ModelId($id), $ids));
    }
}
