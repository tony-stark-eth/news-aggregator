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

    public function testMbStrtolowerOnSearchTextWithUmlauts(): void
    {
        // Keywords in lowercase, article title in uppercase with umlauts
        // mb_strtolower("ÜBER") = "über", strtolower("ÜBER") = "Über" (wrong)
        $rule = $this->createRule(['über']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('ÜBER die Nachrichten');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['über'], $resultsArray[0]->matchedKeywords);
    }

    public function testMbStrtolowerOnKeywordWithUmlauts(): void
    {
        // Keyword in uppercase, article text in lowercase
        // mb_strtolower("MÜNCHEN") = "münchen", strtolower would fail
        $rule = $this->createRule(['MÜNCHEN']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('News from münchen today');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['MÜNCHEN'], $resultsArray[0]->matchedKeywords);
    }

    public function testSearchTextIncludesAllArticleFields(): void
    {
        // Verify title + contentText + summary are all searched
        // Kills MethodCallRemoval on concatenation parts
        $rule1 = $this->createRule(['titleword']);
        $rule2 = $this->createRule(['contentword']);
        $rule3 = $this->createRule(['summaryword']);

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule1, $rule2, $rule3]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(false);

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('titleword here', 'contentword here', 'summaryword here');

        $results = $matcher->match($article);

        self::assertCount(3, $results);
    }

    public function testCooldownUsesCorrectModifier(): void
    {
        // Verify the cooldown cutoff uses the rule's cooldown minutes
        $rule = $this->createRule(['earthquake']);
        $rule->setCooldownMinutes(120); // 2 hours
        $idProp = new \ReflectionProperty(AlertRule::class, 'id');
        $idProp->setValue($rule, 99);

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule]);

        $logRepo = $this->createMock(NotificationLogRepositoryInterface::class);
        $logRepo->expects(self::once())->method('existsRecentForRule')
            ->with(
                99,
                self::callback(static function (\DateTimeImmutable $cutoff): bool {
                    // Clock is at 2026-04-04 12:00:00, cooldown is 120 min
                    // cutoff should be 2026-04-04 10:00:00
                    return $cutoff->format('Y-m-d H:i:s') === '2026-04-04 10:00:00';
                }),
            )
            ->willReturn(false);

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $matcher->match($article);
    }

    public function testMatchedKeywordsOrderPreserved(): void
    {
        // Verify exact keywords list is returned (kills ArrayItemRemoval)
        $rule = $this->createRule(['alpha', 'beta', 'gamma']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('alpha beta gamma content here');

        $results = $matcher->match($article);
        $resultsArray = $results->toArray();

        self::assertCount(1, $resultsArray);
        self::assertSame(['alpha', 'beta', 'gamma'], $resultsArray[0]->matchedKeywords);
    }

    public function testCategoryFilterContinueVsBreak(): void
    {
        // First rule fails category filter, second should still match
        // Kills continue → break mutation on category filter
        $rule1 = $this->createRule(['earthquake']);
        $rule1->setCategories(['sports']); // Won't match 'tech' article

        $rule2 = $this->createRule(['earthquake']);
        $rule2->setCategories([]); // Matches all

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule1, $rule2]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(false);

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        // With continue: skips rule1, matches rule2 → 1 result
        // With break: stops after rule1 → 0 results
        self::assertCount(1, $results);
    }

    public function testCooldownContinueVsBreak(): void
    {
        // First rule in cooldown, second should still match
        $rule1 = $this->createRule(['earthquake']);
        $idProp = new \ReflectionProperty(AlertRule::class, 'id');
        $idProp->setValue($rule1, 101);

        $rule2 = $this->createRule(['earthquake']);
        // rule2 has no ID → skips cooldown check

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule1, $rule2]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(true); // rule1 in cooldown

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        // With continue: skips rule1 (cooldown), matches rule2 → 1 result
        // With break: stops → 0 results
        self::assertCount(1, $results);
    }

    public function testNoKeywordMatchContinueVsBreak(): void
    {
        // First rule has no matching keywords, second should still match
        $rule1 = $this->createRule(['tornado']);
        $rule2 = $this->createRule(['earthquake']);

        $alertRuleRepo = $this->createStub(AlertRuleRepositoryInterface::class);
        $alertRuleRepo->method('findEnabled')->willReturn([$rule1, $rule2]);

        $logRepo = $this->createStub(NotificationLogRepositoryInterface::class);
        $logRepo->method('existsRecentForRule')->willReturn(false);

        $matcher = new ArticleMatcherService($alertRuleRepo, $logRepo, new MockClock('2026-04-04 12:00:00'));
        $article = $this->createArticle('Major earthquake strikes');

        $results = $matcher->match($article);

        // With continue: skips rule1 (no match), matches rule2 → 1 result
        // With break: stops → 0 results
        self::assertCount(1, $results);
    }

    public function testSpaceSeparatorBetweenTitleAndContent(): void
    {
        // Kills ConcatOperandRemoval (removing ' ' between title and contentText)
        // If the space is removed, keywords spanning the boundary won't match
        $rule = $this->createRule(['end start']);
        $matcher = $this->createMatcher($rule);
        // Title ends with "end", content starts with "start"
        // With space: "...end start..." → "end start" found
        // Without space: "...endstart..." → "end start" NOT found
        $article = $this->createArticle('something end', 'start something');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testSpaceSeparatorBetweenContentAndSummary(): void
    {
        // Kills ConcatOperandRemoval (removing ' ' between contentText and summary)
        $rule = $this->createRule(['content_end summary_start']);
        $matcher = $this->createMatcher($rule);
        $article = $this->createArticle('title here', 'text content_end', 'summary_start text');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
    }

    public function testConcatOrderPreserved(): void
    {
        // Kills Concat mutations that rearrange operands
        // Keyword that only matches when title comes first in the search text
        $rule = $this->createRule(['title_unique']);
        $matcher = $this->createMatcher($rule);
        // keyword only in title, not in content or summary
        $article = $this->createArticle('title_unique thing', 'other content', 'other summary');

        $results = $matcher->match($article);

        self::assertCount(1, $results);
        self::assertSame(['title_unique'], $results->toArray()[0]->matchedKeywords);
    }

    public function testNullContentAndSummaryStillSearchable(): void
    {
        // Tests that null contentText and summary don't break concatenation
        $rule = $this->createRule(['titleword']);
        $matcher = $this->createMatcher($rule);

        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Src', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('titleword here', 'https://example.com/' . random_int(1, 99999), $source, new \DateTimeImmutable());
        $article->setCategory($category);
        // contentText and summary are null

        $results = $matcher->match($article);

        self::assertCount(1, $results);
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
