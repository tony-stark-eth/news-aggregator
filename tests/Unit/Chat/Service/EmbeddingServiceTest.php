<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Chat\Service\EmbeddingService;
use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(EmbeddingService::class)]
#[UsesClass(ModelId::class)]
#[UsesClass(ModelIdCollection::class)]
final class EmbeddingServiceTest extends TestCase
{
    public function testEmbedReturnsVectorOnSuccess(): void
    {
        $expectedEmbedding = [0.1, 0.2, 0.3];
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'data' => [[
                'embedding' => $expectedEmbedding,
            ]],
        ], JSON_THROW_ON_ERROR)));

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(
            new ModelIdCollection([new ModelId('test-model')]),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with(
                self::stringContains('Generated embedding'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'test-model' && $ctx['dimensions'] === 3),
            );

        $service = new EmbeddingService($httpClient, $discovery, $logger);
        $result = $service->embed('test text');

        self::assertSame($expectedEmbedding, $result);
    }

    public function testEmbedReturnsNullForEmptyText(): void
    {
        $httpClient = new MockHttpClient();
        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed(''));
    }

    public function testEmbedReturnsNullForWhitespaceOnlyText(): void
    {
        $httpClient = new MockHttpClient();
        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed('   '));
    }

    public function testEmbedReturnsNullWhenNoModelsAvailable(): void
    {
        $httpClient = new MockHttpClient();
        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(new ModelIdCollection());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with('No embedding models available');

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed('some text'));
    }

    public function testEmbedFailsOverToNextModel(): void
    {
        $expectedEmbedding = [0.4, 0.5];
        $responses = [
            new MockResponse('', [
                'error' => 'timeout',
            ]),
            new MockResponse(json_encode([
                'data' => [[
                    'embedding' => $expectedEmbedding,
                ]],
            ], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient($responses);

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(
            new ModelIdCollection([new ModelId('failing-model'), new ModelId('working-model')]),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Embedding request failed'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'failing-model'),
            );
        $logger->expects(self::once())->method('debug')
            ->with(
                self::stringContains('Generated embedding'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'working-model' && $ctx['dimensions'] === 2),
            );

        $service = new EmbeddingService($httpClient, $discovery, $logger);
        $result = $service->embed('test text');

        self::assertSame($expectedEmbedding, $result);
    }

    public function testEmbedReturnsNullWhenAllModelsFail(): void
    {
        $responses = [
            new MockResponse('', [
                'error' => 'timeout',
            ]),
            new MockResponse('', [
                'error' => 'timeout',
            ]),
        ];
        $httpClient = new MockHttpClient($responses);

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(
            new ModelIdCollection([new ModelId('model-1'), new ModelId('model-2')]),
        );

        $logger = $this->createMock(LoggerInterface::class);
        // Two failure warnings + one "all failed" warning
        $logger->expects(self::exactly(3))->method('warning');

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed('test text'));
    }

    public function testEmbedReturnsNullOnEmptyEmbeddingResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'data' => [[
                'embedding' => [],
            ]],
        ], JSON_THROW_ON_ERROR)));

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(
            new ModelIdCollection([new ModelId('test-model')]),
        );

        /** @var list<string> $warningMessages */
        $warningMessages = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning')
            ->willReturnCallback(static function (string $message) use (&$warningMessages): void {
                $warningMessages[] = $message;
            });

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed('test text'));
        self::assertStringContainsString('Empty embedding response', $warningMessages[0]);
        self::assertStringContainsString('All embedding models failed', $warningMessages[1]);
    }

    public function testEmbedReturnsNullOnMissingDataKey(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'other' => 'value',
        ], JSON_THROW_ON_ERROR)));

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverEmbeddingModels')->willReturn(
            new ModelIdCollection([new ModelId('test-model')]),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('warning');

        $service = new EmbeddingService($httpClient, $discovery, $logger);

        self::assertNull($service->embed('test text'));
    }
}
