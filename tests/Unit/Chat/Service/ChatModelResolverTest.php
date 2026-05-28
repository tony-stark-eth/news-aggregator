<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Chat\Service\ChatModelResolver;
use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use App\Shared\Service\SettingsServiceInterface;
use App\Shared\ValueObject\AiProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatModelResolver::class)]
final class ChatModelResolverTest extends TestCase
{
    private function createOpenRouterSettings(): SettingsServiceInterface
    {
        $settings = $this->createStub(SettingsServiceInterface::class);
        $settings->method('getAiProvider')->willReturn(AiProvider::OpenRouter);
        $settings->method('isOpenAiConfigured')->willReturn(false);

        return $settings;
    }

    public function testResolveModelReturnsFirstDiscoveredModel(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([
                new ModelId('google/gemini-flash'),
                new ModelId('openai/gpt-4o-mini'),
            ]));

        $settings = $this->createOpenRouterSettings();

        $resolver = new ChatModelResolver($discovery, $settings);

        self::assertSame('google/gemini-flash', $resolver->resolveModel());
    }

    public function testResolveModelReturnsFallbackWhenNoModels(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([]));

        $settings = $this->createOpenRouterSettings();

        $resolver = new ChatModelResolver($discovery, $settings);

        self::assertSame('openrouter/free', $resolver->resolveModel());
    }

    public function testResolveModelChainReturnsAllDiscoveredModels(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([
                new ModelId('google/gemini-flash'),
                new ModelId('openai/gpt-4o-mini'),
                new ModelId('meta/llama-3'),
            ]));

        $settings = $this->createOpenRouterSettings();

        $resolver = new ChatModelResolver($discovery, $settings);

        self::assertSame(
            ['google/gemini-flash', 'openai/gpt-4o-mini', 'meta/llama-3'],
            $resolver->resolveModelChain(),
        );
    }

    public function testResolveModelChainReturnsFallbackWhenNoModels(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::once())->method('discoverToolCallingModels')
            ->willReturn(new ModelIdCollection([]));

        $settings = $this->createOpenRouterSettings();

        $resolver = new ChatModelResolver($discovery, $settings);

        self::assertSame(['openrouter/free'], $resolver->resolveModelChain());
    }

    public function testResolveModelChainUsesConfiguredOpenAiModel(): void
    {
        $discovery = $this->createMock(ModelDiscoveryServiceInterface::class);
        $discovery->expects(self::never())->method('discoverToolCallingModels');

        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->method('getAiProvider')->willReturn(AiProvider::OpenAi);
        $settings->method('isOpenAiConfigured')->willReturn(true);
        $settings->method('getOpenAiModel')->willReturn('qwen-local');

        $resolver = new ChatModelResolver($discovery, $settings);

        self::assertSame(['qwen-local'], $resolver->resolveModelChain());
        self::assertSame('qwen-local', $resolver->resolveModel());
    }
}
