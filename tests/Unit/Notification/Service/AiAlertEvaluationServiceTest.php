<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\AiAlertEvaluationService;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\EvaluationResult;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

#[CoversClass(AiAlertEvaluationService::class)]
#[UsesClass(EvaluationResult::class)]
final class AiAlertEvaluationServiceTest extends TestCase
{
    public function testSuccessfulAiEvaluation(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 8\nEXPLANATION: Critical vulnerability affecting enterprise systems");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertSame(8, $result->severity);
        self::assertSame('Critical vulnerability affecting enterprise systems', $result->explanation);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testParsesMinSeverity(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 1\nEXPLANATION: Minor issue with no impact");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertSame(1, $result->severity);
        self::assertSame('Minor issue with no impact', $result->explanation);
        self::assertSame('openrouter/free', $result->modelUsed);
    }

    public function testParsesMaxSeverity(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 10\nEXPLANATION: Catastrophic failure imminent");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertSame(10, $result->severity);
        self::assertSame('Catastrophic failure imminent', $result->explanation);
    }

    public function testReturnsNullForSeverityZero(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 0\nEXPLANATION: Nothing");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testReturnsNullForSeverityAboveTen(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 11\nEXPLANATION: Something");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testReturnsNullForEmptyExplanation(): void
    {
        $platform = new InMemoryPlatform("SEVERITY: 5\nEXPLANATION:  ");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testReturnsNullForMissingSeverity(): void
    {
        $platform = new InMemoryPlatform('EXPLANATION: Something happened');
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testReturnsNullForMissingExplanation(): void
    {
        $platform = new InMemoryPlatform('SEVERITY: 5');
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testReturnsNullForGarbageResponse(): void
    {
        $platform = new InMemoryPlatform("I don't know what to do with this");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertNull($result);
    }

    public function testFallsBackToRuleBasedOnAiFailure(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiAlertEvaluationService($platform, new NullLogger());
        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertGreaterThanOrEqual(1, $result->severity);
        self::assertLessThanOrEqual(10, $result->severity);
        self::assertNull($result->modelUsed);
        self::assertSame('Rule-based severity estimate based on keyword overlap', $result->explanation);
    }

    public function testFallsBackWhenContextPromptIsNull(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        // Platform should NOT be called since no context prompt
        $platform->expects(self::never())->method('invoke');

        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('No Context', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        // contextPrompt defaults to null

        $result = $service->evaluate($this->createArticle(), $rule);

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertNull($result->modelUsed);
    }

    public function testFallsBackWhenContextPromptIsEmpty(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::never())->method('invoke');

        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Empty Context', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setContextPrompt('');

        $result = $service->evaluate($this->createArticle(), $rule);

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertNull($result->modelUsed);
    }

    public function testRuleBasedFallbackCalculatesOverlapCorrectly(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiAlertEvaluationService($platform, new NullLogger());

        // Rule with context words that appear in the article
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Overlap Test', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setKeywords(['security']);
        $rule->setContextPrompt('critical security vulnerability software enterprise');

        // Article text: "Critical Security Flaw" + summary has "critical vulnerability was discovered in major software"
        $article = $this->createArticle();

        $result = $service->evaluate($article, $rule);

        self::assertInstanceOf(EvaluationResult::class, $result);
        // "critical" (>3 chars, found), "security" (>3 chars, found), "vulnerability" (>3 chars, found),
        // "software" (>3 chars, found), "enterprise" (>3 chars, not found in article text)
        // overlap=4, severity = min(10, max(1, round(4*2))) = 8
        self::assertSame(8, $result->severity);
    }

    public function testRuleBasedFallbackIgnoresShortWords(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Short Words', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        // Only short words (<=3 chars) — should count as 0 overlap
        $rule->setContextPrompt('the and for is');

        $result = $service->evaluate($this->createArticle(), $rule);

        self::assertInstanceOf(EvaluationResult::class, $result);
        // overlap=0, severity = min(10, max(1, round(0*2))) = max(1, 0) = 1
        self::assertSame(1, $result->severity);
    }

    public function testRuleBasedFallbackCapsAtTen(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Many Overlaps', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        // Many words that appear in the article (title + summary)
        $rule->setContextPrompt('critical security vulnerability discovered major software flaw');

        $article = $this->createArticle();
        $result = $service->evaluate($article, $rule);

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertLessThanOrEqual(10, $result->severity);
        self::assertGreaterThanOrEqual(1, $result->severity);
    }

    public function testArticleWithoutSummaryStillEvaluates(): void
    {
        // Article without summary — AI should still work, using title as fallback
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Critical Security Flaw', 'https://example.com/1', $source, new \DateTimeImmutable());
        // No summary set — getSummary() returns null, so title is used as fallback

        $platform = new InMemoryPlatform("SEVERITY: 5\nEXPLANATION: Moderate issue detected");

        $service = new AiAlertEvaluationService($platform, new NullLogger());
        $result = $service->evaluate($article, $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertSame(5, $result->severity);
        self::assertSame('Moderate issue detected', $result->explanation);
    }

    public function testParsesResponseWithWhitespace(): void
    {
        $platform = new InMemoryPlatform("  SEVERITY: 7  \n  EXPLANATION: Whitespace padded response  ");
        $service = new AiAlertEvaluationService($platform, new NullLogger());

        $result = $service->evaluate($this->createArticle(), $this->createRule());

        self::assertInstanceOf(EvaluationResult::class, $result);
        self::assertSame(7, $result->severity);
        self::assertSame('Whitespace padded response', $result->explanation);
    }

    public function testLoggerCalledOnFailureWithContext(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $platform->method('invoke')->willThrowException(new \RuntimeException('API down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('AI alert evaluation failed'),
                self::callback(static function (array $context): bool {
                    return array_key_exists('rule', $context)
                        && array_key_exists('rule_id', $context)
                        && array_key_exists('article', $context)
                        && array_key_exists('article_id', $context)
                        && array_key_exists('model', $context)
                        && array_key_exists('error', $context)
                        && $context['rule'] === 'Security Alert'
                        && $context['model'] === 'openrouter/free'
                        && $context['error'] === 'API down';
                }),
            );

        $service = new AiAlertEvaluationService($platform, $logger);
        $service->evaluate($this->createArticle(), $this->createRule());
    }

    private function createArticle(): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Critical Security Flaw', 'https://example.com/1', $source, new \DateTimeImmutable());
        $article->setSummary('A critical vulnerability was discovered in major software.');

        return $article;
    }

    private function createRule(): AlertRule
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Security Alert', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $rule->setKeywords(['vulnerability', 'security']);
        $rule->setContextPrompt('Monitor for critical cybersecurity vulnerabilities affecting enterprise software');

        return $rule;
    }
}
