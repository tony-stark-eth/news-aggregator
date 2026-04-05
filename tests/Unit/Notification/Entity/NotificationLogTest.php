<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Entity;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Entity\NotificationLog;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\DeliveryStatus;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationLog::class)]
final class NotificationLogTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Rule', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Title', 'https://example.com/1', $source, new \DateTimeImmutable());

        $log = new NotificationLog($rule, $article, 'keyword', true, new \DateTimeImmutable('2026-04-04 12:00:00'));

        self::assertNull($log->getId());
        self::assertSame($rule, $log->getAlertRule());
        self::assertSame($article, $log->getArticle());
        self::assertSame('keyword', $log->getMatchType());
        self::assertTrue($log->isSuccess());
        self::assertNull($log->getAiSeverity());
        self::assertNull($log->getAiExplanation());
        self::assertSame(DeliveryStatus::Sent, $log->getDeliveryStatus());
    }

    public function testSetAiFields(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Rule', AlertRuleType::Ai, $user, new \DateTimeImmutable());
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Title', 'https://example.com/2', $source, new \DateTimeImmutable());

        $log = new NotificationLog($rule, $article, 'ai', true, new \DateTimeImmutable());
        $log->setAiSeverity(8);
        $log->setAiExplanation('Critical policy change affecting markets');
        $log->setAiModelUsed('openrouter/auto');
        $log->setTransport('pushover');

        self::assertSame(8, $log->getAiSeverity());
        self::assertSame('Critical policy change affecting markets', $log->getAiExplanation());
        self::assertSame('openrouter/auto', $log->getAiModelUsed());
        self::assertSame('pushover', $log->getTransport());
    }

    public function testDeliveryStatusCanBeSet(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Rule', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Title', 'https://example.com/3', $source, new \DateTimeImmutable());

        $log = new NotificationLog($rule, $article, 'keyword', false, new \DateTimeImmutable());
        $log->setDeliveryStatus(DeliveryStatus::Skipped);

        self::assertSame(DeliveryStatus::Skipped, $log->getDeliveryStatus());

        $log->setDeliveryStatus(DeliveryStatus::Failed);

        self::assertSame(DeliveryStatus::Failed, $log->getDeliveryStatus());
    }
}
