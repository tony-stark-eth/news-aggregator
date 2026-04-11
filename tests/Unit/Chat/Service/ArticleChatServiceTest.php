<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Service;

use App\Chat\Service\ArticleChatService;
use App\Chat\Store\ConversationMessageStoreInterface;
use App\Chat\ValueObject\ChatResponse;
use App\Shared\AI\Platform\ModelFailoverPlatform;
use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\AI\ValueObject\ModelId;
use App\Shared\AI\ValueObject\ModelIdCollection;
use App\Shared\AI\ValueObject\ModelQualityCategory;
use App\Shared\Service\SettingsServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

#[CoversClass(ArticleChatService::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(ModelFailoverPlatform::class)]
#[UsesClass(ModelIdCollection::class)]
#[UsesClass(ModelId::class)]
#[UsesClass(ModelQualityCategory::class)]
final class ArticleChatServiceTest extends TestCase
{
    public function testChatReturnsResponseWithAnswer(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('setConversationId')->with('conv-1');
        $store->expects(self::once())->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save')
            ->with(self::callback(static function (MessageBag $bag): bool {
                $messages = $bag->getMessages();

                return \count($messages) >= 2;
            }));

        $platform = $this->createPlatformReturning('This is the answer.');
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance')
            ->with('model-a', ModelQualityCategory::Chat);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $response = $service->chat('What happened today?', 'conv-1');

        self::assertSame('This is the answer.', $response->answer);
        self::assertSame('conv-1', $response->conversationId);
        self::assertSame([], $response->citedArticleIds);
    }

    public function testChatExtractsCitedArticleIds(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $answer = 'See [Article #42] and [Article #99] for details.';
        $platform = $this->createPlatformReturning($answer);
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $response = $service->chat('Tell me about AI', 'conv-2');

        self::assertSame([42, 99], $response->citedArticleIds);
    }

    public function testChatExtractsSingleCitedId(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $answer = 'Check [Article #7] for more info.';
        $platform = $this->createPlatformReturning($answer);
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $this->createStub(LoggerInterface::class), $this->createSettingsStub());
        $response = $service->chat('query', 'conv-x');

        self::assertSame([7], $response->citedArticleIds);
    }

    public function testChatFallsBackWhenNoToolCallingModels(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $platform = $this->createPlatformReturning('Fallback answer.');
        $discovery = $this->createToolCallingDiscovery([]);
        $toolbox = $this->createEmptyToolbox();

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordAcceptance')
            ->with('openrouter/free', ModelQualityCategory::Chat);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::stringContains('No tool-calling models'));

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $response = $service->chat('Hello', 'conv-3');

        self::assertSame('Fallback answer.', $response->answer);
    }

    public function testGetHistoryLoadsFromStore(): void
    {
        $expectedBag = new MessageBag();
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->expects(self::once())->method('setConversationId')->with('conv-5');
        $store->expects(self::once())->method('load')->willReturn($expectedBag);

        $platform = $this->createStub(PlatformInterface::class);
        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $result = $service->getHistory('conv-5');

        self::assertSame($expectedBag, $result);
    }

    public function testChatNoCitedIdsWhenNoPatternMatches(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $platform = $this->createPlatformReturning('No articles found.');
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $response = $service->chat('random question', 'conv-6');

        self::assertSame([], $response->citedArticleIds);
    }

    public function testChatWithMultipleModelsBuildsFailoverPlatform(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $platform = $this->createPlatformReturning('Multi-model answer.');
        $discovery = $this->createToolCallingDiscovery(['model-a', 'model-b', 'model-c']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $logger = $this->createStub(LoggerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());
        $response = $service->chat('Hello', 'conv-7');

        self::assertSame('Multi-model answer.', $response->answer);
    }

    public function testChatSavesBothUserAndAssistantMessages(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save')
            ->with(self::callback(static function (MessageBag $bag): bool {
                $messages = $bag->getMessages();
                $hasUser = false;
                $hasAssistant = false;

                foreach ($messages as $msg) {
                    if ($msg instanceof UserMessage) {
                        $hasUser = true;
                    }
                    if ($msg instanceof AssistantMessage) {
                        $hasAssistant = true;
                    }
                }

                return $hasUser && $hasAssistant;
            }));

        $platform = $this->createPlatformReturning('Reply text.');
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $this->createStub(LoggerInterface::class), $this->createSettingsStub());
        $service->chat('User message', 'conv-save');
    }

    public function testChatPassesUserMessageToPlatform(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->method('save');

        $textResult = new TextResult('Answer.');
        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getData')->willReturn([
            'model' => 'test-model',
        ]);
        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);
        $converter->method('getTokenUsageExtractor')->willReturn(null);
        $deferred = new DeferredResult($converter, $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())->method('invoke')
            ->with(
                self::anything(),
                self::callback(static function (mixed $input): bool {
                    if (! $input instanceof MessageBag) {
                        return false;
                    }

                    return array_any($input->getMessages(), fn (object $msg): bool => $msg instanceof UserMessage);
                }),
            )
            ->willReturn($deferred);

        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $this->createStub(LoggerInterface::class), $this->createSettingsStub());
        $response = $service->chat('My question', 'conv-msg');

        self::assertSame('Answer.', $response->answer);
    }

    public function testChatWithEmptyAnswerFromPlatform(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());
        $store->expects(self::once())->method('save');

        $platform = $this->createPlatformReturning('');
        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();
        $tracker = $this->createStub(ModelQualityTrackerInterface::class);

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $this->createStub(LoggerInterface::class), $this->createSettingsStub());
        $response = $service->chat('question', 'conv-8');

        self::assertSame('', $response->answer);
        self::assertSame([], $response->citedArticleIds);
    }

    public function testChatRecordsRejectionOnFailure(): void
    {
        $store = $this->createMock(ConversationMessageStoreInterface::class);
        $store->method('load')->willReturn(new MessageBag());

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $discovery = $this->createToolCallingDiscovery(['model-a']);
        $toolbox = $this->createEmptyToolbox();

        $tracker = $this->createMock(ModelQualityTrackerInterface::class);
        $tracker->expects(self::once())->method('recordRejection')
            ->with('model-a', ModelQualityCategory::Chat);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with(
                self::stringContains('Chat call failed'),
                self::callback(static fn (array $ctx): bool => $ctx['model'] === 'model-a' && $ctx['error'] === 'API down'),
            );

        $service = new ArticleChatService($store, $platform, $discovery, $toolbox, $tracker, $logger, $this->createSettingsStub());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API down');
        $service->chat('Hello', 'conv-fail');
    }

    /**
     * @param list<string> $modelIds
     */
    private function createToolCallingDiscovery(array $modelIds): ModelDiscoveryServiceInterface
    {
        $models = new ModelIdCollection(
            array_map(static fn (string $id): ModelId => new ModelId($id), $modelIds),
        );

        $discovery = $this->createStub(ModelDiscoveryServiceInterface::class);
        $discovery->method('discoverToolCallingModels')->willReturn($models);

        return $discovery;
    }

    private function createPlatformReturning(string $text): PlatformInterface
    {
        $textResult = new TextResult($text);

        $rawResult = $this->createStub(RawResultInterface::class);
        $rawResult->method('getData')->willReturn([
            'model' => 'test-model',
        ]);

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        $deferred = new DeferredResult($converter, $rawResult);

        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn($deferred);

        return $platform;
    }

    private function createEmptyToolbox(): ToolboxInterface
    {
        $toolbox = $this->createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn([]);

        return $toolbox;
    }

    private function createSettingsStub(): SettingsServiceInterface
    {
        $settings = $this->createStub(SettingsServiceInterface::class);
        $settings->method('getSentimentSlider')->willReturn(0);

        return $settings;
    }
}
