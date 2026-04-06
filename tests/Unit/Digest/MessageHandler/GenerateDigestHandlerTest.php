<?php

declare(strict_types=1);

namespace App\Tests\Unit\Digest\MessageHandler;

use App\Article\Entity\Article;
use App\Article\ValueObject\ArticleCollection;
use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\MessageHandler\GenerateDigestHandler;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepositoryInterface;
use App\Digest\Service\DigestGeneratorServiceInterface;
use App\Digest\Service\DigestSummaryServiceInterface;
use App\Digest\ValueObject\GroupedArticles;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[CoversClass(GenerateDigestHandler::class)]
#[UsesClass(GenerateDigestMessage::class)]
#[UsesClass(GroupedArticles::class)]
final class GenerateDigestHandlerTest extends TestCase
{
    private MockObject&DigestConfigRepositoryInterface $configRepository;

    private MockObject&DigestLogRepositoryInterface $logRepository;

    private MockObject&DigestGeneratorServiceInterface $generator;

    private MockObject&DigestSummaryServiceInterface $summary;

    private MockObject&NotifierInterface $notifier;

    private MockObject&LoggerInterface $logger;

    private MockClock $clock;

    private GenerateDigestHandler $handler;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(DigestConfigRepositoryInterface::class);
        $this->logRepository = $this->createMock(DigestLogRepositoryInterface::class);
        $this->generator = $this->createMock(DigestGeneratorServiceInterface::class);
        $this->summary = $this->createMock(DigestSummaryServiceInterface::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clock = new MockClock();

        $this->handler = new GenerateDigestHandler(
            $this->configRepository,
            $this->logRepository,
            $this->generator,
            $this->summary,
            $this->notifier,
            $this->clock,
            $this->logger,
        );
    }

    public function testSuccessfulRunStoresArticleTitlesAndUpdatesLastRunAt(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());

        $article1 = $this->createMock(Article::class);
        $article1->method('getTitle')->willReturn('Article One');
        $article2 = $this->createMock(Article::class);
        $article2->method('getTitle')->willReturn('Article Two');

        $collection = new ArticleCollection([$article1, $article2]);
        $grouped = new GroupedArticles([
            'tech' => $collection,
        ]);

        $this->configRepository->method('findById')->willReturn($config);
        $this->generator->method('collectArticles')->willReturn($grouped);
        $this->summary->method('generate')->willReturn('Summary content');

        $this->notifier->expects(self::once())->method('send')
            ->with(self::callback(static function (Notification $n): bool {
                return str_contains($n->getSubject(), 'Test') && str_contains($n->getSubject(), '2 articles');
            }));

        $this->configRepository->expects(self::once())->method('flush');

        $this->logger->expects(self::once())->method('info')
            ->with(
                'Digest "{name}" generated: {count} articles',
                self::callback(static fn (array $ctx): bool => $ctx['name'] === 'Test' && $ctx['count'] === 2 && $ctx['delivery_success'] === true && $ctx['digest_config_id'] === 1),
            );

        $savedLog = null;
        $this->logRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (DigestLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }));

        ($this->handler)(new GenerateDigestMessage(1));

        self::assertNotNull($savedLog);
        self::assertSame(['Article One', 'Article Two'], $savedLog->getArticleTitles());
        self::assertSame(2, $savedLog->getArticleCount());
        self::assertTrue($savedLog->isDeliverySuccess());
        self::assertSame('Summary content', $savedLog->getContent());
        self::assertEquals($this->clock->now(), $config->getLastRunAt());
    }

    public function testSkippedRunIsLoggedWithLastRunAtUpdate(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());

        $grouped = new GroupedArticles([]);

        $this->configRepository->method('findById')->willReturn($config);
        $this->generator->method('collectArticles')->willReturn($grouped);

        $this->configRepository->expects(self::once())->method('flush');

        $this->logger->expects(self::once())->method('info')
            ->with(
                'Digest "{name}" skipped: no articles found',
                self::callback(static fn (array $ctx): bool => $ctx['name'] === 'Test' && $ctx['digest_config_id'] === 1),
            );

        $savedLog = null;
        $this->logRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (DigestLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }));

        ($this->handler)(new GenerateDigestMessage(1));

        self::assertNotNull($savedLog);
        self::assertSame(0, $savedLog->getArticleCount());
        self::assertFalse($savedLog->isDeliverySuccess());
        self::assertSame([], $savedLog->getArticleTitles());
        self::assertStringContainsString('Skipped', $savedLog->getContent());
        self::assertEquals($this->clock->now(), $config->getLastRunAt());
    }

    public function testForceFlagBypassesEnabledCheck(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());
        $config->setEnabled(false);

        $grouped = new GroupedArticles([]);

        $this->configRepository->method('findById')->willReturn($config);
        $this->generator->method('collectArticles')->willReturn($grouped);

        $this->logRepository
            ->expects(self::once())
            ->method('save');

        ($this->handler)(new GenerateDigestMessage(1, force: true));
    }

    public function testDisabledConfigIsSkippedWithoutForce(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());
        $config->setEnabled(false);

        $this->configRepository->method('findById')->willReturn($config);

        $this->generator->expects(self::never())->method('collectArticles');
        $this->logRepository->expects(self::never())->method('save');

        ($this->handler)(new GenerateDigestMessage(1));
    }

    public function testNotificationFailureLogsWarningAndSetsSuccessFalse(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());

        $article = $this->createMock(Article::class);
        $article->method('getTitle')->willReturn('Title');
        $collection = new ArticleCollection([$article]);
        $grouped = new GroupedArticles([
            'tech' => $collection,
        ]);

        $this->configRepository->method('findById')->willReturn($config);
        $this->generator->method('collectArticles')->willReturn($grouped);
        $this->summary->method('generate')->willReturn('Content');
        $this->notifier->method('send')->willThrowException(new \RuntimeException('Transport error'));

        $this->logger->expects(self::exactly(2))->method(self::anything());

        $savedLog = null;
        $this->logRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (DigestLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }));

        ($this->handler)(new GenerateDigestMessage(1));

        self::assertNotNull($savedLog);
        self::assertFalse($savedLog->isDeliverySuccess());
    }
}
