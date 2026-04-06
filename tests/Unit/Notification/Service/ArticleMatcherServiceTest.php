<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Notification\Service\ArticleMatcherService;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\MatchResult;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ArticleMatcherService::class)]
#[UsesClass(MatchResult::class)]
final class ArticleMatcherServiceTest extends TestCase
{
    public function testMatchesKeywordInTitle(): void
    {
        $rule = $this->createRule(['earthquake', 'tsunami']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Major earthquake strikes region');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['earthquake'], $resultsArray[0]->matchedKeywords);
        self::assertSame($rule, $resultsArray[0]->alertRule);
    }

    public function testMatchesKeywordInContent(): void
    {
        $rule = $this->createRule(['vulnerability']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Security Update', 'Critical vulnerability found in software');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['vulnerability'], $resultsArray[0]->matchedKeywords);
    }

    public function testMatchesKeywordInSummary(): void
    {
        $rule = $this->createRule(['exploit']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Security Update', null, 'A new exploit was discovered');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['exploit'], $resultsArray[0]->matchedKeywords);
    }

    public function testMatchesMultipleKeywords(): void
    {
        $rule = $this->createRule(['earthquake', 'tsunami', 'disaster']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Earthquake triggers tsunami disaster');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertCount(3, $resultsArray[0]->matchedKeywords);
        self::assertSame(['earthquake', 'tsunami', 'disaster'], $resultsArray[0]->matchedKeywords);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $rule = $this->createRule(['hurricane', 'tornado']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Tech company launches new product');

        $results = $matcher->match($article);

        self::assertCount(0, $results);
    }

    public function testMatchIsCaseInsensitive(): void
    {
        $rule = $this->createRule(['EARTHQUAKE']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Major Earthquake strikes');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testRespectsCategories(): void
    {
        $rule = $this->createRule(['breaking']);
        $rule->setCategories(['sports']); // Only match sports

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Breaking news in tech'); // Category is 'tech'

        $results = $matcher->match($article);

        self::assertCount(0, $results); // Category mismatch
    }

    public function testEmptyCategoriesMatchesAll(): void
    {
        $rule = $this->createRule(['breaking']);
        $rule->setCategories([]); // Empty = match all

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Breaking news in tech');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testMatchesCategoryWhenArticleHasMatchingCategory(): void
    {
        $rule = $this->createRule(['breaking']);
        $rule->setCategories(['tech', 'science']);

        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Breaking news in tech'); // Category is 'tech'

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testArticleWithoutCategoryFailsCategoryFilter(): void
    {
        $rule = $this->createRule(['breaking']);
        $rule->setCategories(['tech']);

        $matcher = $this->createMatcher($rule);

        // Article with no category set
        $source = new Source('Src', 'https://example.com/feed', new Category('Tech', 'tech', 10, '#3B82F6'), new \DateTimeImmutable());
        $article = new Article('Breaking news', 'https://example.com/' . random_int(1, 99999), $source, new \DateTimeImmutable());
        // No category set -> getCategory() returns null -> getSlug() is null

        $results = $matcher->match($article);

        self::assertCount(0, $results);
    }

    public function testSkipsRuleInCooldown(): void
    {
        $rule = $this->createRule(['earthquake']);
        // Set a persisted ID so cooldown check actually runs
        $idProp = new \ReflectionProperty(AlertRule::class, 'id');
        $idProp->setValue($rule, 42);

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(true); // In cooldown

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        self::assertCount(0, $results);
    }

    public function testRuleWithNullIdSkipsCooldownCheck(): void
    {
        // A rule with no persisted ID (id=null) should not be cooldown-checked
        $rule = $this->createRule(['earthquake']); // id is null since not persisted

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule]);

        $logRepo = $this->createMock(NotificationLogRepositoryInterface::class);
        // existsRecentForRule should NOT be called since ruleId is null
        $logRepo->expects(self::never())->method('existsRecentForRule');

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testMultipleRulesCanMatch(): void
    {
        $rule1 = $this->createRule(['earthquake']);
        $rule2 = $this->createRule(['major']);

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule1, $rule2]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(false);

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        self::assertCount(2, $results);
    }

    public function testEmptyKeywordsDoNotMatch(): void
    {
        $rule = $this->createRule([]);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('Any title here');

        $results = $matcher->match($article);

        self::assertCount(0, $results);
    }

    /**
     * @param list<string> $keywords
     */
    private function createRule(array $keywords): AlertRule
    {
        $user = new User('admin@example.com', 'hashed');
        $rule = new AlertRule('Test Rule', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords($keywords);

        return $rule;
    }

    private function createMatcher(AlertRule $rule): ArticleMatcherService
    {
        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(false);

        return new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
    }

    private function createArticle(string $title = 'Test Article', ?string $content = null, ?string $summary = null): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article($title, 'https://example.com/' . random_int(1, 99999), $source, new \DateTimeImmutable());
        $article->setContentText($content);
        $article->setSummary($summary);
        $article->setCategory($category);

        return $article;
    }
}
