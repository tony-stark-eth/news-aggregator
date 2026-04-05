<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Entity\NotificationLog;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Notification\Service\NotificationDispatchService;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\DeliveryStatus;
use App\Notification\ValueObject\EvaluationResult;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Notifier\NotifierInterface;

#[CoversClass(NotificationDispatchService::class)]
#[UsesClass(NotificationLog::class)]
#[UsesClass(DeliveryStatus::class)]
final class NotificationDispatchServiceTest extends TestCase
{
    /**
     * @var NotifierInterface&MockObject
     */
    private MockObject $notifier;

    /**
     * @var NotificationLogRepositoryInterface&MockObject
     */
    private MockObject $logRepository;

    private MockClock $clock;

    private AlertRule $rule;

    private Article $article;

    protected function setUp(): void
    {
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->logRepository = $this->createMock(NotificationLogRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-05 12:00:00'));

        $user = new User('admin@example.com', 'hashed');
        $this->rule = new AlertRule('Test Rule', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $this->article = new Article('Test Article', 'https://example.com/1', $source, new \DateTimeImmutable());
    }

    public function testDispatchWithTransportLogsSent(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'pushover://USER@TOKEN',
        );

        $this->notifier->expects(self::once())->method('send');

        $savedLog = null;
        $this->logRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (NotificationLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }), true);

        $service->dispatch($this->rule, $this->article, ['bitcoin']);

        self::assertInstanceOf(NotificationLog::class, $savedLog);
        self::assertSame(DeliveryStatus::Sent, $savedLog->getDeliveryStatus());
        self::assertTrue($savedLog->isSuccess());
    }

    public function testDispatchWithoutTransportLogsSkipped(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'null://null',
        );

        $this->notifier->expects(self::never())->method('send');

        $savedLog = null;
        $this->logRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (NotificationLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }), true);

        $service->dispatch($this->rule, $this->article, ['bitcoin']);

        self::assertInstanceOf(NotificationLog::class, $savedLog);
        self::assertSame(DeliveryStatus::Skipped, $savedLog->getDeliveryStatus());
        self::assertFalse($savedLog->isSuccess());
    }

    public function testDispatchWithEmptyDsnLogsSkipped(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            '',
        );

        $this->notifier->expects(self::never())->method('send');

        $savedLog = null;
        $this->logRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (NotificationLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }), true);

        $service->dispatch($this->rule, $this->article, ['bitcoin']);

        self::assertInstanceOf(NotificationLog::class, $savedLog);
        self::assertSame(DeliveryStatus::Skipped, $savedLog->getDeliveryStatus());
    }

    public function testDispatchWithTransportFailureLogsFailed(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'pushover://USER@TOKEN',
        );

        $this->notifier->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Transport error'));

        $savedLog = null;
        $this->logRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (NotificationLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }), true);

        $service->dispatch($this->rule, $this->article, ['bitcoin']);

        self::assertInstanceOf(NotificationLog::class, $savedLog);
        self::assertSame(DeliveryStatus::Failed, $savedLog->getDeliveryStatus());
        self::assertFalse($savedLog->isSuccess());
    }

    public function testDispatchWithAiEvaluationSetsFields(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'pushover://USER@TOKEN',
        );

        $evaluation = new EvaluationResult(8, 'Critical supply chain disruption', 'openrouter/auto');

        $savedLog = null;
        $this->logRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (NotificationLog $log) use (&$savedLog): bool {
                $savedLog = $log;

                return true;
            }), true);

        $service->dispatch($this->rule, $this->article, ['bitcoin'], $evaluation);

        self::assertInstanceOf(NotificationLog::class, $savedLog);
        self::assertSame(8, $savedLog->getAiSeverity());
        self::assertSame('Critical supply chain disruption', $savedLog->getAiExplanation());
        self::assertSame('openrouter/auto', $savedLog->getAiModelUsed());
        self::assertSame('ai', $savedLog->getMatchType());
    }

    public function testHasTransportReturnsFalseForNullDsn(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'null://null',
        );

        self::assertFalse($service->hasTransport());
    }

    public function testHasTransportReturnsTrueForRealDsn(): void
    {
        $service = new NotificationDispatchService(
            $this->notifier,
            $this->logRepository,
            $this->clock,
            'pushover://USER@TOKEN',
        );

        self::assertTrue($service->hasTransport());
    }
}
