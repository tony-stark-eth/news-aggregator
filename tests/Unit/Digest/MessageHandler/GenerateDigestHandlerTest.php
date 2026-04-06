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
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
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

    private MockClock $clock;

    private GenerateDigestHandler $handler;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(DigestConfigRepositoryInterface::class);
        $this->logRepository = $this->createMock(DigestLogRepositoryInterface::class);
        $this->generator = $this->createMock(DigestGeneratorServiceInterface::class);
        $this->summary = $this->createMock(DigestSummaryServiceInterface::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->clock = new MockClock();

        $this->handler = new GenerateDigestHandler(
            $this->configRepository,
            $this->logRepository,
            $this->generator,
            $this->summary,
            $this->notifier,
            $this->clock,
            new NullLogger(),
        );
    }

    public function testArticleTitlesAreStoredInLog(): void
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
    }

    public function testSkippedRunIsLogged(): void
    {
        $user = new User('test@example.com', 'hashed');
        $config = new DigestConfig('Test', '0 8 * * *', $user, new \DateTimeImmutable());

        $grouped = new GroupedArticles([]);

        $this->configRepository->method('findById')->willReturn($config);
        $this->generator->method('collectArticles')->willReturn($grouped);

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
}
