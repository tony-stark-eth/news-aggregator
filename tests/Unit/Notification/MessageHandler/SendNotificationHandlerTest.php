<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Notification\Entity\AlertRule;
use App\Notification\Message\SendNotificationMessage;
use App\Notification\MessageHandler\SendNotificationHandler;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Service\AiAlertEvaluationServiceInterface;
use App\Notification\Service\NotificationDispatchServiceInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\EvaluationResult;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(SendNotificationHandler::class)]
#[UsesClass(SendNotificationMessage::class)]
#[UsesClass(EvaluationResult::class)]
final class SendNotificationHandlerTest extends TestCase
{
    private MockObject&AlertRuleRepositoryInterface $alertRuleRepository;

    private MockObject&ArticleRepositoryInterface $articleRepository;

    private MockObject&NotificationDispatchServiceInterface $dispatchService;

    private MockObject&AiAlertEvaluationServiceInterface $aiEvaluationService;

    private SendNotificationHandler $handler;

    private AlertRule $rule;

    private Article $article;

    protected function setUp(): void
    {
        $this->alertRuleRepository = $this->createMock(AlertRuleRepositoryInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->dispatchService = $this->createMock(NotificationDispatchServiceInterface::class);
        $this->aiEvaluationService = $this->createMock(AiAlertEvaluationServiceInterface::class);

        $this->handler = new SendNotificationHandler(
            $this->alertRuleRepository,
            $this->articleRepository,
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

        $this->alertRuleRepository->method('findById')->willReturn($this->rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($this->rule, $this->article, ['bitcoin'], null);

        ($this->handler)($message);
    }

    public function testSkipsWhenRuleNotFound(): void
    {
        $message = new SendNotificationMessage(999, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn(null);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->dispatchService->expects(self::never())->method('dispatch');
        $this->aiEvaluationService->expects(self::never())->method('evaluate');

        ($this->handler)($message);
    }

    public function testSkipsWhenArticleNotFound(): void
    {
        $message = new SendNotificationMessage(1, 999, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($this->rule);
        $this->articleRepository->method('findById')->willReturn(null);

        $this->dispatchService->expects(self::never())->method('dispatch');
        $this->aiEvaluationService->expects(self::never())->method('evaluate');

        ($this->handler)($message);
    }

    public function testSkipsWhenRuleDisabled(): void
    {
        $this->rule->setEnabled(false);
        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($this->rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->dispatchService->expects(self::never())->method('dispatch');
        $this->aiEvaluationService->expects(self::never())->method('evaluate');

        ($this->handler)($message);
    }

    public function testAiEvaluationPassedToDispatch(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $evaluation = new EvaluationResult(8, 'Critical event', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->expects(self::once())
            ->method('evaluate')
            ->with($this->article, $rule)
            ->willReturn($evaluation);

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($rule, $this->article, ['bitcoin'], $evaluation);

        ($this->handler)($message);
    }

    public function testBothTypeRuleTriggersAiEvaluation(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Both Rule', AlertRuleType::Both, $user, new \DateTimeImmutable());
        $evaluation = new EvaluationResult(9, 'Very critical', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->expects(self::once())
            ->method('evaluate')
            ->willReturn($evaluation);

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($rule, $this->article, ['bitcoin'], $evaluation);

        ($this->handler)($message);
    }

    public function testKeywordTypeRuleSkipsAiEvaluation(): void
    {
        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($this->rule); // Type: Keyword
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->expects(self::never())->method('evaluate');

        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($this->rule, $this->article, ['bitcoin'], null);

        ($this->handler)($message);
    }

    public function testAiEvaluationBelowThresholdSkipsDispatch(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setSeverityThreshold(7);
        $evaluation = new EvaluationResult(3, 'Low impact', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->method('evaluate')->willReturn($evaluation);

        $this->dispatchService->expects(self::never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testAiEvaluationAtExactThresholdDispatches(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setSeverityThreshold(5);
        $evaluation = new EvaluationResult(5, 'Exactly threshold', 'openrouter/auto');

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->method('evaluate')->willReturn($evaluation);

        // Severity 5 is NOT less than threshold 5 -> should dispatch
        $this->dispatchService->expects(self::once())->method('dispatch');

        ($this->handler)($message);
    }

    public function testNullAiEvaluationStillDispatches(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setSeverityThreshold(5);

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);

        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        $this->aiEvaluationService->method('evaluate')->willReturn(null);

        // Null evaluation is NOT an instance of EvaluationResult, so severity check is skipped
        $this->dispatchService->expects(self::once())
            ->method('dispatch')
            ->with($rule, $this->article, ['bitcoin'], null);

        ($this->handler)($message);
    }

    public function testLoggerInfoOnSuccessfulDispatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('Notification sent'),
                self::callback(static function (array $context): bool {
                    return array_key_exists('rule', $context)
                        && array_key_exists('rule_id', $context)
                        && array_key_exists('article', $context)
                        && array_key_exists('article_id', $context)
                        && $context['rule'] === 'Test Rule'
                        && $context['article'] === 'Test Article';
                }),
            );

        $handler = new SendNotificationHandler(
            $this->alertRuleRepository,
            $this->articleRepository,
            $this->dispatchService,
            $this->aiEvaluationService,
            $logger,
        );

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);
        $this->alertRuleRepository->method('findById')->willReturn($this->rule);
        $this->articleRepository->method('findById')->willReturn($this->article);

        ($handler)($message);
    }

    public function testLoggerInfoWhenAiSeverityBelowThreshold(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('AI Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setSeverityThreshold(7);
        $evaluation = new EvaluationResult(3, 'Low impact', 'openrouter/auto');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with(
                self::stringContains('skipped'),
                self::callback(static function (array $context): bool {
                    return array_key_exists('rule', $context)
                        && array_key_exists('rule_id', $context)
                        && array_key_exists('article_id', $context)
                        && array_key_exists('severity', $context)
                        && array_key_exists('threshold', $context)
                        && $context['severity'] === 3
                        && $context['threshold'] === 7;
                }),
            );

        $handler = new SendNotificationHandler(
            $this->alertRuleRepository,
            $this->articleRepository,
            $this->dispatchService,
            $this->aiEvaluationService,
            $logger,
        );

        $message = new SendNotificationMessage(1, 1, ['bitcoin']);
        $this->alertRuleRepository->method('findById')->willReturn($rule);
        $this->articleRepository->method('findById')->willReturn($this->article);
        $this->aiEvaluationService->method('evaluate')->willReturn($evaluation);

        ($handler)($message);
    }
}
