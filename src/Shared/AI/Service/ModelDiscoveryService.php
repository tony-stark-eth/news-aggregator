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
    private const string CACHE_KEY = 'openrouter_free_models';

    private const int CACHE_TTL = 3600;

    private const string BREAKER_KEY = 'openrouter_cb';

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
        $state = $this->getState();

        if ($state === CircuitBreakerState::Open) {
            $this->logger->debug('Circuit breaker open, using cached model list');

            return $this->getCachedModels();
        }

        // Closed state: check model cache first
        if ($state === CircuitBreakerState::Closed) {
            $cacheItem = $this->cache->getItem(self::CACHE_KEY);
            if ($cacheItem->isHit()) {
                /** @var list<string> $cached */
                $cached = $cacheItem->get();

                return $this->toCollection($cached);
            }
        }

        // Closed (cache miss) or HalfOpen: probe the API
        if ($state === CircuitBreakerState::HalfOpen) {
            $this->logger->debug('Circuit breaker half-open, attempting probe request');
        }

        return $this->fetchWithCircuitBreaker($state);
    }

    private function fetchWithCircuitBreaker(CircuitBreakerState $state): ModelIdCollection
    {
        try {
            $models = $this->fetchFreeModels();

            $this->resetBreaker();
            $this->cacheModels($models);

            $this->logger->info('Discovered {count} free OpenRouter models', [
                'count' => $models->count(),
            ]);

            return $models;
        } catch (\Throwable $e) {
            return $this->handleFailure($e, $state);
        }
    }

    private function handleFailure(\Throwable $e, CircuitBreakerState $state): ModelIdCollection
    {
        $failures = $this->incrementFailures();

        $this->logger->warning('Model discovery failed ({count}/{threshold}): {error}', [
            'count' => $failures,
            'threshold' => self::BREAKER_THRESHOLD,
            'error' => $e->getMessage(),
        ]);

        // HalfOpen probe failed or threshold reached: open the breaker
        if ($state === CircuitBreakerState::HalfOpen || $failures >= self::BREAKER_THRESHOLD) {
            $this->openBreaker();
        }

        return $this->getCachedModels();
    }

    /**
     * @return array{state: string, failures: int, opened_at: ?int}
     */
    private function getBreakerData(): array
    {
        $item = $this->cache->getItem(self::BREAKER_KEY);
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

    private function getState(): CircuitBreakerState
    {
        $data = $this->getBreakerData();
        $state = CircuitBreakerState::tryFrom($data['state']) ?? CircuitBreakerState::Closed;

        if ($state !== CircuitBreakerState::Open) {
            return $state;
        }

        // Check if Open state has expired → transition to HalfOpen
        $openedAt = $data['opened_at'];
        if ($openedAt !== null) {
            $elapsed = $this->clock->now()->getTimestamp() - $openedAt;
            if ($elapsed >= self::BREAKER_RESET_SECONDS) {
                $this->saveBreakerData(CircuitBreakerState::HalfOpen, $data['failures'], $openedAt);

                return CircuitBreakerState::HalfOpen;
            }
        }

        return CircuitBreakerState::Open;
    }

    private function incrementFailures(): int
    {
        $data = $this->getBreakerData();
        $failures = $data['failures'] + 1;
        $state = CircuitBreakerState::tryFrom($data['state']) ?? CircuitBreakerState::Closed;
        $this->saveBreakerData($state, $failures, $data['opened_at']);

        return $failures;
    }

    private function openBreaker(): void
    {
        $this->saveBreakerData(
            CircuitBreakerState::Open,
            self::BREAKER_THRESHOLD,
            $this->clock->now()->getTimestamp(),
        );

        $this->logger->warning('Circuit breaker opened for model discovery ({seconds}s)', [
            'seconds' => self::BREAKER_RESET_SECONDS,
        ]);
    }

    private function resetBreaker(): void
    {
        $this->cache->deleteItem(self::BREAKER_KEY);
    }

    private function saveBreakerData(CircuitBreakerState $state, int $failures, ?int $openedAt): void
    {
        $item = $this->cache->getItem(self::BREAKER_KEY);
        $item->set([
            'state' => $state->value,
            'failures' => $failures,
            'opened_at' => $openedAt,
        ]);
        // Long TTL — state transitions managed in code, not by cache expiry
        $item->expiresAfter(self::BREAKER_RESET_SECONDS * 2);
        $this->cache->save($item);
    }

    private function cacheModels(ModelIdCollection $models): void
    {
        $rawIds = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        $cacheItem->set($rawIds);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);
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
