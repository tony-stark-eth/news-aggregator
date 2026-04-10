<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\AI\ValueObject\CircuitBreakerState;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelDiscoveryService::class)]
#[UsesClass(CircuitBreakerState::class)]
#[UsesClass(ModelId::class)]
#[UsesClass(ModelIdCollection::class)]
final class ModelDiscoveryServiceTest extends TestCase
{
    private const string BREAKER_KEY = 'openrouter_cb';

    private const string CACHE_KEY = 'openrouter_free_models';

    private const string TOOL_CALLING_BREAKER_KEY = 'openrouter_tool_calling_cb';

    private const string TOOL_CALLING_CACHE_KEY = 'openrouter_tool_calling_models';

    private const string EMBEDDING_BREAKER_KEY = 'openrouter_embedding_cb';

    private const string EMBEDDING_CACHE_KEY = 'openrouter_embedding_models';

    public function testDiscoversFreeModels(): void
    {
        $service = $this->createService($this->successClient());

        $models = $service->discoverFreeModels();

        self::assertCount(2, $models);
        self::assertContainsOnlyInstancesOf(ModelId::class, $models->toArray());

        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('free-model-1', $values);
        self::assertContains('free-model-2', $values);
        self::assertNotContains('paid-model', $values);
        self::assertNotContains('free-small', $values);
    }

    public function testFiltersPaidModels(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'free-one',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ],
            [
                'id' => 'paid-prompt',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0.001',
                    'completion' => '0',
                ],
            ],
            [
                'id' => 'paid-completion',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0.002',
                ],
            ],
        ]));

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('free-one', $first->value);
    }

    public function testFiltersModelsBelowMinContextLength(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'large-context',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ],
            [
                'id' => 'small-context',
                'context_length' => 4096,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ],
        ]));

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('large-context', $first->value);
    }

    public function testFilterBlockedModels(): void
    {
        $service = $this->createService(
            $this->clientWithModels([
                [
                    'id' => 'good-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
                [
                    'id' => 'blocked-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
            ]),
            blockedModels: 'blocked-model',
        );

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-model', $first->value);
    }

    public function testFilterMultipleBlockedModels(): void
    {
        $service = $this->createService(
            $this->clientWithModels([
                [
                    'id' => 'good-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
                [
                    'id' => 'blocked-1',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
                [
                    'id' => 'blocked-2',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
            ]),
            blockedModels: 'blocked-1, blocked-2',
        );

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
    }

    public function testCachesResults(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount): MockResponse {
            $callCount++;

            return new MockResponse($this->modelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory));

        $service->discoverFreeModels();
        $service->discoverFreeModels();

        self::assertSame(1, $callCount);
    }

    public function testCacheStoresModelIds(): void
    {
        $cache = new ArrayAdapter();
        $service = $this->createService($this->successClient(), cache: $cache);

        $service->discoverFreeModels();

        $cacheItem = $cache->getItem(self::CACHE_KEY);
        self::assertTrue($cacheItem->isHit());

        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertContains('free-model-1', $cached);
        self::assertContains('free-model-2', $cached);
        self::assertNotContains('paid-model', $cached);
    }

    public function testCircuitBreakerOpensAfterThresholdFailures(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');
        $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);

        // Three failures should open circuit breaker (persisted across instances)
        $service->discoverFreeModels();

        // Simulate new request (new service instance, same cache)
        $service2 = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $service2->discoverFreeModels();

        $service3 = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $service3->discoverFreeModels();

        // Verify breaker is open in cache
        $breakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertTrue($breakerItem->isHit());

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $breakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);
        self::assertSame(3, $data['failures']);
        self::assertSame($clock->now()->getTimestamp(), $data['opened_at']);
    }

    public function testOpenBreakerReturnsCachedModelsWithoutApiCall(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Pre-populate model cache
        $populateService = $this->createService($this->successClient(), cache: $cache, clock: $clock);
        $populateService->discoverFreeModels();

        // Open the breaker (opened_at = now, so not expired)
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // New call should return cached models without hitting API
        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse('error', [
                'error' => 'should not be called',
            ]);
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $models = $service->discoverFreeModels();

        self::assertSame(0, $apiCallCount);
        self::assertCount(2, $models);
    }

    public function testOpenBreakerWithNoCacheReturnsEmpty(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open breaker without any cached models
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $models = $service->discoverFreeModels();

        self::assertCount(0, $models);
    }

    public function testHalfOpenAfterResetPeriod(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open breaker at time 0
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Advance clock past reset period (24h)
        $clock->modify('+25 hours');

        // Service should detect expired Open -> HalfOpen -> probe API
        $service = $this->createService($this->successClient(), cache: $cache, clock: $clock);
        $models = $service->discoverFreeModels();

        self::assertCount(2, $models);

        // Breaker should be reset after successful probe
        $breakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertFalse($breakerItem->isHit());
    }

    public function testHalfOpenProbeFailureReopensBreaker(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open breaker at time 0
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Advance clock past reset period
        $clock->modify('+25 hours');

        // Probe request fails
        $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $service->discoverFreeModels();

        // Breaker should be re-opened
        $breakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertTrue($breakerItem->isHit());

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $breakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);
        self::assertSame(3, $data['failures']);
        self::assertNotNull($data['opened_at']);
    }

    public function testBreakerStaysOpenBeforeResetPeriod(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open breaker at time 0
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Advance only 23 hours — not enough
        $clock->modify('+23 hours');

        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse($this->modelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $service->discoverFreeModels();

        // Should NOT have called API — breaker is still open
        self::assertSame(0, $apiCallCount);
    }

    public function testFailureCountPersistsAcrossInstances(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Two failures from separate instances
        $service1 = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $service1->discoverFreeModels();

        $service2 = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $service2->discoverFreeModels();

        // Verify failure count is 2 (not 1)
        $breakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertTrue($breakerItem->isHit());

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $breakerItem->get();
        self::assertSame(2, $data['failures']);
        self::assertSame(CircuitBreakerState::Closed->value, $data['state']);
    }

    public function testSuccessfulFetchResetsBreaker(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Set one failure
        $failService = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $failService->discoverFreeModels();

        // Successful fetch should delete breaker data
        $successService = $this->createService($this->successClient(), cache: $cache, clock: $clock);
        // Clear model cache to force API call
        $cache->deleteItem(self::CACHE_KEY);
        $successService->discoverFreeModels();

        $breakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertFalse($breakerItem->isHit());
    }

    public function testHandlesEmptyDataArray(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'data' => [],
        ], JSON_THROW_ON_ERROR)));
        $service = $this->createService($client);

        $models = $service->discoverFreeModels();

        self::assertCount(0, $models);
    }

    public function testHandlesMissingDataKey(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([], JSON_THROW_ON_ERROR)));
        $service = $this->createService($client);

        $models = $service->discoverFreeModels();

        self::assertCount(0, $models);
    }

    public function testLoggerInfoOnSuccessfulDiscovery(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(self::stringContains('Discovered'), self::callback(static fn (array $ctx): bool => $ctx['count'] === 2 && $ctx['pool'] === 'free'));

        $service = new ModelDiscoveryService(
            $this->successClient(),
            new ArrayAdapter(),
            new MockClock(),
            $logger,
        );
        $service->discoverFreeModels();
    }

    public function testLoggerWarningOnFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::stringContains('failed'));

        $service = new ModelDiscoveryService(
            $this->failingClient(),
            new ArrayAdapter(),
            new MockClock(),
            $logger,
        );
        $service->discoverFreeModels();
    }

    public function testLoggerWarningOnBreakerOpen(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        $logger = $this->createMock(LoggerInterface::class);
        // Should log "Circuit breaker opened" when threshold hit
        $logger->expects(self::atLeastOnce())->method('warning');

        // Three consecutive failures to trigger breaker
        for ($i = 0; $i < 3; $i++) {
            $service = new ModelDiscoveryService(
                $this->failingClient(),
                $cache,
                $clock,
                $logger,
            );
            $service->discoverFreeModels();
        }
    }

    public function testHalfOpenDebugLogOnlyWhenHalfOpen(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());
        $clock->modify('+25 hours');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with(self::stringContains('half-open'));

        $service = new ModelDiscoveryService(
            $this->successClient(),
            $cache,
            $clock,
            $logger,
        );
        $service->discoverFreeModels();
    }

    public function testOpenBreakerLogsWithSecondsContext(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        /** @var list<array{message: string, context: array<string, mixed>}> $warningCalls */
        $warningCalls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(
            static function (string $message, array $context) use (&$warningCalls): void {
                $warningCalls[] = [
                    'message' => $message,
                    'context' => $context,
                ];
            },
        );

        for ($i = 0; $i < 3; $i++) {
            $service = new ModelDiscoveryService(
                $this->failingClient(),
                $cache,
                $clock,
                $logger,
            );
            $service->discoverFreeModels();
        }

        $found = false;
        foreach ($warningCalls as $call) {
            if (str_contains($call['message'], 'Circuit breaker opened')) {
                $found = true;
                self::assertArrayHasKey('seconds', $call['context']);
                self::assertSame(86400, $call['context']['seconds']);
            }
        }
        self::assertTrue($found, 'Expected "Circuit breaker opened" warning was not logged');
    }

    public function testDiscoversToolCallingModels(): void
    {
        $service = $this->createService($this->toolCallingClient());

        $models = $service->discoverToolCallingModels();

        self::assertCount(2, $models);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('tool-model-1', $values);
        self::assertContains('tool-model-2', $values);
        self::assertNotContains('no-tools-model', $values);
        self::assertNotContains('paid-tool-model', $values);
    }

    public function testToolCallingFiltersModelsWithoutToolsParam(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'has-tools',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'tools',
                    'temperature',
                ],
            ],
            [
                'id' => 'no-tools',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'temperature',
                ],
            ],
            [
                'id' => 'missing-params',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ],
        ]));

        $models = $service->discoverToolCallingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('has-tools', $first->value);
    }

    public function testToolCallingFiltersPaidModels(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'free-tools',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'tools',
                ],
            ],
            [
                'id' => 'paid-tools',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0.001',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'tools',
                ],
            ],
        ]));

        $models = $service->discoverToolCallingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('free-tools', $first->value);
    }

    public function testToolCallingFiltersSmallContextModels(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'large-context-tools',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'tools',
                ],
            ],
            [
                'id' => 'small-context-tools',
                'context_length' => 4096,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'tools',
                ],
            ],
        ]));

        $models = $service->discoverToolCallingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('large-context-tools', $first->value);
    }

    public function testToolCallingFiltersBlockedModels(): void
    {
        $service = $this->createService(
            $this->clientWithModels([
                [
                    'id' => 'good-tool-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'supported_parameters' => [
                        'tools',
                    ],
                ],
                [
                    'id' => 'blocked-tool-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'supported_parameters' => [
                        'tools',
                    ],
                ],
            ]),
            blockedModels: 'blocked-tool-model',
        );

        $models = $service->discoverToolCallingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-tool-model', $first->value);
    }

    public function testToolCallingCachesResults(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount): MockResponse {
            $callCount++;

            return new MockResponse($this->toolCallingModelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory));

        $service->discoverToolCallingModels();
        $service->discoverToolCallingModels();

        self::assertSame(1, $callCount);
    }

    public function testToolCallingCacheStoresModelIds(): void
    {
        $cache = new ArrayAdapter();
        $service = $this->createService($this->toolCallingClient(), cache: $cache);

        $service->discoverToolCallingModels();

        $cacheItem = $cache->getItem(self::TOOL_CALLING_CACHE_KEY);
        self::assertTrue($cacheItem->isHit());

        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertContains('tool-model-1', $cached);
        self::assertContains('tool-model-2', $cached);
    }

    public function testToolCallingUsesSeparateCacheFromFreeModels(): void
    {
        $cache = new ArrayAdapter();
        $json = $this->toolCallingModelsJson();
        $client = new MockHttpClient([
            new MockResponse($json),
            new MockResponse($json),
        ]);
        $service = $this->createService($client, cache: $cache);

        $service->discoverFreeModels();
        $service->discoverToolCallingModels();

        $freeCacheItem = $cache->getItem(self::CACHE_KEY);
        $toolCacheItem = $cache->getItem(self::TOOL_CALLING_CACHE_KEY);

        self::assertTrue($freeCacheItem->isHit());
        self::assertTrue($toolCacheItem->isHit());

        /** @var list<string> $freeIds */
        $freeIds = $freeCacheItem->get();
        /** @var list<string> $toolIds */
        $toolIds = $toolCacheItem->get();

        // Free models pool should be larger (includes models without tool support)
        self::assertGreaterThan(\count($toolIds), \count($freeIds));
    }

    public function testToolCallingCircuitBreakerIsIndependent(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open the FREE models breaker
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Tool-calling breaker should still be closed — API call should happen
        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse($this->toolCallingModelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $models = $service->discoverToolCallingModels();

        self::assertSame(1, $apiCallCount);
        self::assertCount(2, $models);
    }

    public function testToolCallingBreakerOpensIndependently(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Three failures on tool-calling should open its own breaker
        for ($i = 0; $i < 3; $i++) {
            $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
            $service->discoverToolCallingModels();
        }

        // Verify tool-calling breaker is open
        $toolBreakerItem = $cache->getItem(self::TOOL_CALLING_BREAKER_KEY);
        self::assertTrue($toolBreakerItem->isHit());

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $toolBreakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);

        // Verify free models breaker is still closed (not in cache)
        $freeBreakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertFalse($freeBreakerItem->isHit());
    }

    public function testToolCallingOpenBreakerReturnsCachedModels(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Pre-populate tool-calling cache
        $populateService = $this->createService($this->toolCallingClient(), cache: $cache, clock: $clock);
        $populateService->discoverToolCallingModels();

        // Open the tool-calling breaker
        $this->setToolCallingBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Should return cached models without API call
        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse('error', [
                'error' => 'should not be called',
            ]);
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $models = $service->discoverToolCallingModels();

        self::assertSame(0, $apiCallCount);
        self::assertCount(2, $models);
    }

    public function testToolCallingOpenBreakerWithNoCacheReturnsEmpty(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        $this->setToolCallingBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $models = $service->discoverToolCallingModels();

        self::assertCount(0, $models);
    }

    public function testToolCallingReturnsEmptyWhenNoToolModelsExist(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'free-no-tools',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'supported_parameters' => [
                    'temperature',
                ],
            ],
        ]));

        $models = $service->discoverToolCallingModels();

        self::assertCount(0, $models);
    }

    public function testToolCallingLoggerInfoOnSuccessfulDiscovery(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('Discovered'),
                self::callback(static fn (array $ctx): bool => $ctx['count'] === 2 && $ctx['pool'] === 'tool-calling'),
            );

        $service = new ModelDiscoveryService(
            $this->toolCallingClient(),
            new ArrayAdapter(),
            new MockClock(),
            $logger,
        );
        $service->discoverToolCallingModels();
    }

    public function testToolCallingLoggerWarningOnFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::stringContains('failed'));

        $service = new ModelDiscoveryService(
            $this->failingClient(),
            new ArrayAdapter(),
            new MockClock(),
            $logger,
        );
        $service->discoverToolCallingModels();
    }

    public function testToolCallingHalfOpenAfterResetPeriod(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        $this->setToolCallingBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        $clock->modify('+25 hours');

        $service = $this->createService($this->toolCallingClient(), cache: $cache, clock: $clock);
        $models = $service->discoverToolCallingModels();

        self::assertCount(2, $models);

        // Breaker should be reset after successful probe
        $breakerItem = $cache->getItem(self::TOOL_CALLING_BREAKER_KEY);
        self::assertFalse($breakerItem->isHit());
    }

    public function testDiscoversEmbeddingModels(): void
    {
        $service = $this->createService($this->embeddingClient());

        $models = $service->discoverEmbeddingModels();

        self::assertCount(2, $models);
        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('embedding-model-1', $values);
        self::assertContains('embedding-model-2', $values);
        self::assertNotContains('no-embed-model', $values);
        self::assertNotContains('paid-embed-model', $values);
    }

    public function testEmbeddingFiltersModelsWithoutEmbeddingModality(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'has-embedding',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'output_modalities' => ['embeddings'],
            ],
            [
                'id' => 'text-only',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'output_modalities' => ['text'],
            ],
            [
                'id' => 'no-modalities',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ],
        ]));

        $models = $service->discoverEmbeddingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('has-embedding', $first->value);
    }

    public function testEmbeddingFiltersPaidModels(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'free-embed',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'output_modalities' => ['embeddings'],
            ],
            [
                'id' => 'paid-embed',
                'context_length' => 8192,
                'pricing' => [
                    'prompt' => '0.001',
                    'completion' => '0',
                ],
                'output_modalities' => ['embeddings'],
            ],
        ]));

        $models = $service->discoverEmbeddingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('free-embed', $first->value);
    }

    public function testEmbeddingFiltersBlockedModels(): void
    {
        $service = $this->createService(
            $this->clientWithModels([
                [
                    'id' => 'good-embed',
                    'context_length' => 8192,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['embeddings'],
                ],
                [
                    'id' => 'blocked-embed',
                    'context_length' => 8192,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['embeddings'],
                ],
            ]),
            blockedModels: 'blocked-embed',
        );

        $models = $service->discoverEmbeddingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-embed', $first->value);
    }

    public function testEmbeddingCachesResults(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount): MockResponse {
            $callCount++;

            return new MockResponse($this->embeddingModelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory));

        $service->discoverEmbeddingModels();
        $service->discoverEmbeddingModels();

        self::assertSame(1, $callCount);
    }

    public function testEmbeddingCacheStoresModelIds(): void
    {
        $cache = new ArrayAdapter();
        $service = $this->createService($this->embeddingClient(), cache: $cache);

        $service->discoverEmbeddingModels();

        $cacheItem = $cache->getItem(self::EMBEDDING_CACHE_KEY);
        self::assertTrue($cacheItem->isHit());

        /** @var list<string> $cached */
        $cached = $cacheItem->get();
        self::assertContains('embedding-model-1', $cached);
        self::assertContains('embedding-model-2', $cached);
    }

    public function testEmbeddingCircuitBreakerIsIndependent(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Open the FREE models breaker
        $this->setBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Embedding breaker should still be closed — API call should happen
        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse($this->embeddingModelsJson());
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $models = $service->discoverEmbeddingModels();

        self::assertSame(1, $apiCallCount);
        self::assertCount(2, $models);
    }

    public function testEmbeddingBreakerOpensIndependently(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Three failures on embedding should open its own breaker
        for ($i = 0; $i < 3; $i++) {
            $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
            $service->discoverEmbeddingModels();
        }

        // Verify embedding breaker is open
        $embeddingBreakerItem = $cache->getItem(self::EMBEDDING_BREAKER_KEY);
        self::assertTrue($embeddingBreakerItem->isHit());

        /** @var array{state: string, failures: int, opened_at: ?int} $data */
        $data = $embeddingBreakerItem->get();
        self::assertSame(CircuitBreakerState::Open->value, $data['state']);

        // Verify free models breaker is still closed (not in cache)
        $freeBreakerItem = $cache->getItem(self::BREAKER_KEY);
        self::assertFalse($freeBreakerItem->isHit());
    }

    public function testEmbeddingOpenBreakerReturnsCachedModels(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        // Pre-populate embedding cache
        $populateService = $this->createService($this->embeddingClient(), cache: $cache, clock: $clock);
        $populateService->discoverEmbeddingModels();

        // Open the embedding breaker
        $this->setEmbeddingBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        // Should return cached models without API call
        $apiCallCount = 0;
        $factory = function () use (&$apiCallCount): MockResponse {
            $apiCallCount++;

            return new MockResponse('error', [
                'error' => 'should not be called',
            ]);
        };

        $service = $this->createService(new MockHttpClient($factory), cache: $cache, clock: $clock);
        $models = $service->discoverEmbeddingModels();

        self::assertSame(0, $apiCallCount);
        self::assertCount(2, $models);
    }

    public function testEmbeddingOpenBreakerWithNoCacheReturnsEmpty(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-01-01 00:00:00');

        $this->setEmbeddingBreakerState($cache, CircuitBreakerState::Open, openedAt: $clock->now()->getTimestamp());

        $service = $this->createService($this->failingClient(), cache: $cache, clock: $clock);
        $models = $service->discoverEmbeddingModels();

        self::assertCount(0, $models);
    }

    public function testEmbeddingDoesNotFilterByContextLength(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'small-context-embed',
                'context_length' => 512,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'output_modalities' => ['embeddings'],
            ],
        ]));

        $models = $service->discoverEmbeddingModels();

        self::assertCount(1, $models);
        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('small-context-embed', $first->value);
    }

    public function testEmbeddingLoggerInfoOnSuccessfulDiscovery(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('Discovered'),
                self::callback(static fn (array $ctx): bool => $ctx['count'] === 2 && $ctx['pool'] === 'embedding'),
            );

        $service = new ModelDiscoveryService(
            $this->embeddingClient(),
            new ArrayAdapter(),
            new MockClock(),
            $logger,
        );
        $service->discoverEmbeddingModels();
    }

    public function testEmbeddingReturnsEmptyWhenNoEmbeddingModelsExist(): void
    {
        $service = $this->createService($this->clientWithModels([
            [
                'id' => 'text-model',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
                'output_modalities' => ['text'],
            ],
        ]));

        $models = $service->discoverEmbeddingModels();

        self::assertCount(0, $models);
    }

    private function createService(
        MockHttpClient $client,
        ?ArrayAdapter $cache = null,
        ?MockClock $clock = null,
        string $blockedModels = '',
    ): ModelDiscoveryService {
        return new ModelDiscoveryService(
            $client,
            $cache ?? new ArrayAdapter(),
            $clock ?? new MockClock(),
            new NullLogger(),
            $blockedModels,
        );
    }

    private function successClient(): MockHttpClient
    {
        return new MockHttpClient(new MockResponse($this->modelsJson()));
    }

    private function failingClient(): MockHttpClient
    {
        return new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));
    }

    /**
     * @param list<array<string, mixed>> $models
     */
    private function clientWithModels(array $models): MockHttpClient
    {
        return new MockHttpClient(new MockResponse(
            json_encode([
                'data' => $models,
            ], JSON_THROW_ON_ERROR),
        ));
    }

    private function modelsJson(): string
    {
        return json_encode([
            'data' => [
                [
                    'id' => 'free-model-1',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
                [
                    'id' => 'paid-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0.001',
                        'completion' => '0.002',
                    ],
                ],
                [
                    'id' => 'free-small',
                    'context_length' => 4096,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
                [
                    'id' => 'free-model-2',
                    'context_length' => 16384,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function toolCallingClient(): MockHttpClient
    {
        return new MockHttpClient(new MockResponse($this->toolCallingModelsJson()));
    }

    private function toolCallingModelsJson(): string
    {
        return json_encode([
            'data' => [
                [
                    'id' => 'tool-model-1',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'supported_parameters' => [
                        'tools',
                        'temperature',
                    ],
                ],
                [
                    'id' => 'no-tools-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'supported_parameters' => [
                        'temperature',
                    ],
                ],
                [
                    'id' => 'paid-tool-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0.001',
                        'completion' => '0.002',
                    ],
                    'supported_parameters' => [
                        'tools',
                    ],
                ],
                [
                    'id' => 'tool-model-2',
                    'context_length' => 16384,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'supported_parameters' => [
                        'tools',
                        'top_p',
                    ],
                ],
                [
                    'id' => 'free-no-params',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function setBreakerState(
        ArrayAdapter $cache,
        CircuitBreakerState $state,
        int $failures = 3,
        ?int $openedAt = null,
    ): void {
        $item = $cache->getItem(self::BREAKER_KEY);
        $item->set([
            'state' => $state->value,
            'failures' => $failures,
            'opened_at' => $openedAt ?? 1_735_689_600,
        ]);
        $item->expiresAfter(172_800);
        $cache->save($item);
    }

    private function setToolCallingBreakerState(
        ArrayAdapter $cache,
        CircuitBreakerState $state,
        int $failures = 3,
        ?int $openedAt = null,
    ): void {
        $item = $cache->getItem(self::TOOL_CALLING_BREAKER_KEY);
        $item->set([
            'state' => $state->value,
            'failures' => $failures,
            'opened_at' => $openedAt ?? 1_735_689_600,
        ]);
        $item->expiresAfter(172_800);
        $cache->save($item);
    }

    private function setEmbeddingBreakerState(
        ArrayAdapter $cache,
        CircuitBreakerState $state,
        int $failures = 3,
        ?int $openedAt = null,
    ): void {
        $item = $cache->getItem(self::EMBEDDING_BREAKER_KEY);
        $item->set([
            'state' => $state->value,
            'failures' => $failures,
            'opened_at' => $openedAt ?? 1_735_689_600,
        ]);
        $item->expiresAfter(172_800);
        $cache->save($item);
    }

    private function embeddingClient(): MockHttpClient
    {
        return new MockHttpClient(new MockResponse($this->embeddingModelsJson()));
    }

    private function embeddingModelsJson(): string
    {
        return json_encode([
            'data' => [
                [
                    'id' => 'embedding-model-1',
                    'context_length' => 8192,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['embeddings'],
                ],
                [
                    'id' => 'no-embed-model',
                    'context_length' => 32768,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['text'],
                ],
                [
                    'id' => 'paid-embed-model',
                    'context_length' => 8192,
                    'pricing' => [
                        'prompt' => '0.001',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['embeddings'],
                ],
                [
                    'id' => 'embedding-model-2',
                    'context_length' => 4096,
                    'pricing' => [
                        'prompt' => '0',
                        'completion' => '0',
                    ],
                    'output_modalities' => ['embeddings'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
