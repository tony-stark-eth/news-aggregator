<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\ArticleMatcherService;
use App\Notification\ValueObject\AlertRuleType;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ArticleMatcherService::class)]
final class ArticleMatcherServiceTest extends TestCase
{
    public function testMatchesKeywordInTitle(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Breaking News', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords(['earthquake', 'tsunami']);

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Major earthquake strikes region');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['earthquake'], $resultsArray[0]->matchedKeywords);
    }

    public function testMatchesKeywordInContent(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Tech Alert', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords(['vulnerability']);

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Security Update', 'Critical vulnerability found in software');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['vulnerability'], $resultsArray[0]->matchedKeywords);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Weather', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords(['hurricane', 'tornado']);

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Tech company launches new product');

        $results = $matcher->match($article);

        self::assertCount(0, $results);
    }

    public function testRespectsCategories(): void
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Sports Only', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords(['breaking']);
        $rule->setCategories(['sports']); // Only match sports

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Breaking news in tech'); // Category is 'tech'

        $results = $matcher->match($article);

        self::assertCount(0, $results); // Category mismatch
    }

    private function createMatcher(AlertRule $rule): ArticleMatcherService
    {
        $ruleRepo = $this->createStub(EntityRepository::class);
        $ruleRepo->method('findBy')->willReturn([$rule]);

        // Cooldown query returns null (not in cooldown)
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $logRepo = $this->createStub(EntityRepository::class);
        $logRepo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): \PHPUnit\Framework\MockObject\Stub => $class === AlertRule::class ? $ruleRepo : $logRepo,
        );

        return new ArticleMatcherService($em, new MockClock('2026-04-04 12:00:00'));
    }

    private function createArticle(string $title = 'Test Article', ?string $content = null): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article($title, 'https://example.com/' . random_int(1, 99999), $source, new \DateTimeImmutable());
        $article->setContentText($content);
        $article->setCategory($category);

        return $article;
    }
}
