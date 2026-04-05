<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\MessageHandler;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\MessageHandler\SendNotificationHandler;
use App\Notification\Service\AiAlertEvaluationServiceInterface;
use App\Notification\Service\NotificationDispatchServiceInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\EvaluationResult;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(SendNotificationHandler::class)]
final class SendNotificationHandlerTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private MockObject $em;

    /**
     * @var NotificationDispatchServiceInterface&MockObject
     */
    private MockObject $dispatchService;

    /**
     * @var AiAlertEvaluationServiceInterface&MockObject
     */
    private MockObject $aiEvaluationService;

    private SendNotificationHandler $handler;

    private AlertRule $rule;

    private Article $article;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->dispatchService = $this->createMock(NotificationDispatchServiceInterface::class);
        $this->aiEvaluationService = $this->createMock(AiAlertEvaluationServiceInterface::class);

        $this->handler = new SendNotificationHandler(
            $this->em,
            $this->dispatchService,
            $this->aiEvaluationService,
            new NullLogger(),
        );

        $user = new User('admin@example.com', 'hashed');
        $this->rule = new AlertRule('Test Rule', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $this->article = new Article('Test Article', 'https://example.com/1', $source, new \DateTimeImmutable());
    }

    public function testDispatchCalledForKeywordMatch(): void
    {
        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->em->method('find')
            ->willReturnCallback(fn (string $class): AlertRule|\App\Article\Entity\Article|null => match ($class) {
                AlertRule::class => $this->rule,
                Article::class => $this->article,
                default => null,
            });

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($this->rule, $this->article, ['bitcoin'], null);

        ($this->handler)($message);
    }

    public function testSkipsWhenRuleNotFound(): void
    {
        $message = new SendNotificationMessage(999, 1, ['bitcoin']);

        $this->em->method('find')
            ->willReturn(null);

        $this->dispatchService->expects(self::never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testSkipsWhenRuleDisabled(): void
    {
        $this->rule->setEnabled(false);
        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->em->method('find')
            ->willReturnCallback(fn (string $class): AlertRule|\App\Article\Entity\Article|null => match ($class) {
                AlertRule::class => $this->rule,
                Article::class => $this->article,
                default => null,
            });

        $this->dispatchService->expects(self::never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testAiEvaluationPassedToDispatch(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $evaluation = new EvaluationResult(8, 'Critical event', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->em->method('find')
            ->willReturnCallback(fn (string $class): AlertRule|\App\Article\Entity\Article|null => match ($class) {
                AlertRule::class => $rule,
                Article::class => $this->article,
                default => null,
            });

        $this->aiEvaluationService->method('evaluate')
            ->willReturn($evaluation);

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($rule, $this->article, ['bitcoin'], $evaluation);

        ($this->handler)($message);
    }

    public function testAiEvaluationBelowThresholdSkipsDispatch(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setSeverityThreshold(7);
        $evaluation = new EvaluationResult(3, 'Low impact', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->em->method('find')
            ->willReturnCallback(fn (string $class): AlertRule|\App\Article\Entity\Article|null => match ($class) {
                AlertRule::class => $rule,
                Article::class => $this->article,
                default => null,
            });

        $this->aiEvaluationService->method('evaluate')
            ->willReturn($evaluation);

        $this->dispatchService->expects(self::never())
            ->method('dispatch');

        ($this->handler)($message);
    }
}
