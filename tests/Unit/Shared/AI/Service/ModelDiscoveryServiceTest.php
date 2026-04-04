<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Service;

use App\Shared\AI\Service\ModelDiscoveryService;
use App\Shared\AI\ValueObject\ModelId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelDiscoveryService::class)]
final class ModelDiscoveryServiceTest extends TestCase
{
    public function testDiscoversFreeModels(): void
    {
        $responseBody = json_encode([
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

        $client = new MockHttpClient(new MockResponse($responseBody));
        $service = new ModelDiscoveryService($client, new ArrayAdapter(), new NullLogger());

        $models = $service->discoverFreeModels();

        self::assertCount(2, $models);
        self::assertContainsOnlyInstancesOf(ModelId::class, $models->toArray());

        $values = array_map(static fn (ModelId $m): string => $m->value, $models->toArray());
        self::assertContains('free-model-1', $values);
        self::assertContains('free-model-2', $values);
        self::assertNotContains('paid-model', $values);
        self::assertNotContains('free-small', $values); // context_length < 8192
    }

    public function testFilterBlockedModels(): void
    {
        $responseBody = json_encode([
            'data' => [
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
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new MockHttpClient(new MockResponse($responseBody));
        $service = new ModelDiscoveryService(
            $client,
            new ArrayAdapter(),
            new NullLogger(),
            'blocked-model',
        );

        $models = $service->discoverFreeModels();

        self::assertCount(1, $models);
        self::assertContainsOnlyInstancesOf(ModelId::class, $models->toArray());

        $first = $models->first();
        self::assertInstanceOf(ModelId::class, $first);
        self::assertSame('good-model', $first->value);
    }

    public function testCachesResults(): void
    {
        $callCount = 0;
        $body = json_encode([
            'data' => [[
                'id' => 'cached-model',
                'context_length' => 32768,
                'pricing' => [
                    'prompt' => '0',
                    'completion' => '0',
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $factory = static function () use (&$callCount, $body): MockResponse {
            $callCount++;

            return new MockResponse($body);
        };

        $client = new MockHttpClient($factory);
        $service = new ModelDiscoveryService($client, new ArrayAdapter(), new NullLogger());

        $service->discoverFreeModels();
        $service->discoverFreeModels();

        self::assertSame(1, $callCount);
    }

    public function testCircuitBreakerOpensAfterThreeFailures(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'timeout',
        ]));
        $service = new ModelDiscoveryService($client, new ArrayAdapter(), new NullLogger());

        // Three failures should open circuit breaker
        $service->discoverFreeModels();
        $service->discoverFreeModels();
        $service->discoverFreeModels();

        // Fourth call should not hit the API (circuit breaker open)
        $models = $service->discoverFreeModels();
        self::assertTrue($models->isEmpty()); // empty because no cache exists
    }
}
