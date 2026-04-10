<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Chat\Service\ChatModelResolver;
use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatModelResolver::class)]
final class ChatModelResolverTest extends TestCase
{
    public function testResolveModelReturnsFirstDiscoveredModel(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([
                new ModelId('google/gemini-flash'),
                new ModelId('openai/gpt-4o-mini'),
            ]));

        $resolver = new ChatModelResolver($discovery);

        self::assertSame('google/gemini-flash', $resolver->resolveModel());
    }

    public function testResolveModelReturnsFallbackWhenNoModels(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([]));

        $resolver = new ChatModelResolver($discovery);

        self::assertSame('openrouter/free', $resolver->resolveModel());
    }
}
