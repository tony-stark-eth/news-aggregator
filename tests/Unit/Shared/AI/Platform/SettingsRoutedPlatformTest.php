<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Platform;

use App\Shared\AI\Platform\SettingsRoutedPlatform;
use App\Shared\Service\SettingsServiceInterface;
use App\Shared\ValueObject\AiProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;

#[CoversClass(SettingsRoutedPlatform::class)]
final class SettingsRoutedPlatformTest extends TestCase
{
    private MockObject&SettingsServiceInterface $settings;

    private MockObject&PlatformInterface $openRouterFailover;

    private MockObject&PlatformInterface $openAiPlatform;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsServiceInterface::class);
        $this->openRouterFailover = $this->createMock(PlatformInterface::class);
        $this->openAiPlatform = $this->createMock(PlatformInterface::class);
    }

    public function testInvokeUsesOpenAiPlatformWhenConfigured(): void
    {
        $this->settings->method('getAiProvider')->willReturn(AiProvider::OpenAi);
        $this->settings->method('isOpenAiConfigured')->willReturn(true);

        $this->openAiPlatform->expects(self::once())
            ->method('invoke')
            ->with('openrouter/free', 'prompt', [])
            ->willThrowException(new \RuntimeException('openai-called'));

        $this->openRouterFailover->expects(self::never())->method('invoke');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('openai-called');

        $this->createPlatform()->invoke('openrouter/free', 'prompt');
    }

    public function testInvokeUsesOpenRouterWhenProviderIsOpenRouter(): void
    {
        $this->settings->method('getAiProvider')->willReturn(AiProvider::OpenRouter);
        $this->settings->method('isOpenAiConfigured')->willReturn(false);

        $this->openRouterFailover->expects(self::once())
            ->method('invoke')
            ->with('openrouter/free', 'prompt', [])
            ->willThrowException(new \RuntimeException('openrouter-called'));

        $this->openAiPlatform->expects(self::never())->method('invoke');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('openrouter-called');

        $this->createPlatform()->invoke('openrouter/free', 'prompt');
    }

    public function testGetModelCatalogDelegatesToActivePlatform(): void
    {
        $catalog = $this->createMock(ModelCatalogInterface::class);

        $this->settings->method('getAiProvider')->willReturn(AiProvider::OpenAi);
        $this->settings->method('isOpenAiConfigured')->willReturn(true);

        $this->openAiPlatform->expects(self::once())
            ->method('getModelCatalog')
            ->willReturn($catalog);

        self::assertSame($catalog, $this->createPlatform()->getModelCatalog());
    }

    private function createPlatform(): SettingsRoutedPlatform
    {
        return new SettingsRoutedPlatform(
            $this->settings,
            $this->openRouterFailover,
            $this->openAiPlatform,
        );
    }
}
