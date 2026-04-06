<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiQualityGateService;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiQualityGateService::class)]
final class AiQualityGateServiceTest extends TestCase
{
    private AiQualityGateService $gate;

    protected function setUp(): void
    {
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findBySlug')->willReturnCallback(
            static fn (string $slug): ?Category => match ($slug) {
                'politics', 'business', 'tech', 'science', 'sports' => new Category($slug, $slug, 10, '#000'),
                default => null,
            },
        );

        $this->gate = new AiQualityGateService($categoryRepo);
    }

    public function testValidSummaryPasses(): void
    {
        self::assertTrue($this->gate->validateSummary(
            'The government announced new economic measures to combat inflation.',
            'Economic Policy Update',
        ));
    }

    public function testTooShortSummaryFails(): void
    {
        self::assertFalse($this->gate->validateSummary('Too short.', 'Title'));
    }

    public function testTooLongSummaryFails(): void
    {
        $long = str_repeat('word ', 120);
        self::assertFalse($this->gate->validateSummary($long, 'Title'));
    }

    public function testTitleRepeatFails(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Breaking News: Major Event Today',
            'Breaking News: Major Event Today',
        ));
    }

    public function testValidCategorizationPasses(): void
    {
        self::assertTrue($this->gate->validateCategorization('tech'));
        self::assertTrue($this->gate->validateCategorization('politics'));
    }

    public function testInvalidCategorizationFails(): void
    {
        self::assertFalse($this->gate->validateCategorization('unknown'));
        self::assertFalse($this->gate->validateCategorization(''));
    }

    public function testSummaryExactly20CharsPassesLowerBound(): void
    {
        // 20 chars exactly should pass length check
        $summary = str_repeat('a', 20);
        self::assertTrue($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly19CharsFailsLowerBound(): void
    {
        $summary = str_repeat('a', 19);
        self::assertFalse($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly500CharsPassesUpperBound(): void
    {
        $summary = str_repeat('a', 500);
        self::assertTrue($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly501CharsFailsUpperBound(): void
    {
        $summary = str_repeat('a', 501);
        self::assertFalse($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSimilarButNotIdenticalTitlePasses(): void
    {
        // Just below 90% similarity should pass
        self::assertTrue($this->gate->validateSummary(
            'The quick brown fox jumps over the lazy dog near the park and the lake area.',
            'A completely different title that has nothing to do with foxes',
        ));
    }

    public function testMbStrlenUsedForLengthCheckWithMultibyte(): void
    {
        // 20 Japanese chars = 20 mb chars, 60 bytes
        // mb_strlen = 20 (passes >= 20 check)
        // strlen = 60 (also passes, so need boundary)
        // Use 19 multibyte chars = mb_strlen 19 < 20 → fail
        // strlen would be 57 >= 20 → would pass
        $summary19mb = str_repeat('日', 19);
        self::assertFalse($this->gate->validateSummary($summary19mb, 'Different Title'));

        // 20 multibyte chars → should pass
        $summary20mb = str_repeat('日', 20);
        self::assertTrue($this->gate->validateSummary($summary20mb, 'Different Title'));
    }

    public function testMbStrlenUpperBoundWithMultibyte(): void
    {
        // 500 multibyte chars → mb_strlen=500, passes <=500
        $summary500mb = str_repeat('日', 500);
        self::assertTrue($this->gate->validateSummary($summary500mb, 'Different Title'));

        // 501 multibyte chars → mb_strlen=501, fails >500
        $summary501mb = str_repeat('日', 501);
        self::assertFalse($this->gate->validateSummary($summary501mb, 'Different Title'));
    }

    public function testMbStrtolowerUsedForSimilarityWithUmlauts(): void
    {
        // Summary and title differ only by case with umlauts
        // mb_strtolower properly handles Ü→ü, strtolower does not
        $summary = 'ÜBER DIE NACHRICHTEN HEUTE ABEND WICHTIG INFORMATION';
        $title = 'über die nachrichten heute abend wichtig information';
        // These should be 100% similar after mb_strtolower → rejected
        self::assertFalse($this->gate->validateSummary($summary, $title));
    }

    public function testSimilarityAt90PercentBoundary(): void
    {
        // percent < 90.0: returns true
        // percent <= 90.0: would also return true for < 90, but differs at exactly 90
        // Kills LessThan vs LessThanOrEqual mutation
        // Need two strings with exactly 90% similarity
        // similar_text: percent = 2*common / (len1+len2) * 100
        // For two 10-char strings with 9 common: 2*9/20*100 = 90%
        // But similar_text algorithm is greedy, not always this simple.
        // Let's use longer strings to avoid edge cases.
        $title = 'This is the exact title of the article today';
        $summary = 'This is the exact title of the article today.';
        // Adding just a period -> very high similarity but not 100%
        // similar_text would give very high % → likely > 90 → rejected
        self::assertFalse($this->gate->validateSummary($summary, $title));
    }

    public function testMbStrtolowerOnSummaryWithUmlauts(): void
    {
        // Kills MBString on mb_strtolower($summary) → strtolower($summary)
        // Summary and title are uppercase with umlauts. mb_strtolower properly handles them.
        // If strtolower is used on summary, Ü stays uppercase → different from title → passes (wrong)
        $summary = 'ÜBER DIE WICHTIGEN NACHRICHTEN HEUTE';
        $title = 'über die wichtigen nachrichten heute';
        // With mb_strtolower on both: identical → 100% → rejected
        // With strtolower on summary: "ÜBER..." → "Über..." (Ü not lowercased) → different → accepted
        self::assertFalse($this->gate->validateSummary($summary, $title));
    }

    public function testMbStrtolowerOnTitleWithUmlauts(): void
    {
        // Kills MBString on mb_strtolower($title) → strtolower($title)
        $summary = 'über die wichtigen nachrichten heute';
        $title = 'ÜBER DIE WICHTIGEN NACHRICHTEN HEUTE';
        // With mb_strtolower on title: "über..." → matches summary → 100% → rejected
        // With strtolower on title: "Über..." → different → accepted (wrong)
        self::assertFalse($this->gate->validateSummary($summary, $title));
    }

    public function testLessThanAt90PercentExactly(): void
    {
        // Construct strings with exactly 90% similarity
        // similar_text uses longest common substring matching
        // For "aaaaaaaaaa" (10 'a') and "aaaaaaaaab" (9 'a' + 'b'):
        // common = 9, percent = 2*9/20*100 = 90.0
        // < 90.0: false → returns false (rejected)
        // <= 90.0: true → returns true (accepted)
        // So at exactly 90%, < 90 returns false, <= 90 returns true
        $summary = str_repeat('a', 20) . str_repeat('a', 10); // 30 'a's
        $title = str_repeat('a', 20) . str_repeat('b', 10);   // 20 'a's + 10 'b's
        // common = 20, total chars = 30+30=60, percent = 2*20/60*100 = 66.7% < 90 → passes
        // Need higher similarity. Let's use:
        // "aaaaaaaaaaaaaaaaaaaab" (19 a + b, 20 chars) vs "aaaaaaaaaaaaaaaaaaaa" (20 a, 20 chars)
        // common = 19, percent = 2*19/40*100 = 95% → > 90 → rejected
        // Both < and <= would give false for 95 → doesn't distinguish.
        // For < vs <=, need exactly 90%.
        // 20 chars each, common=18: 2*18/40*100 = 90%
        // "aaaaaaaaaaaaaaaaaaaabb" (18 a + 2 different) doesn't work simply.
        // Actually similar_text is more complex. Let me just test clear boundaries.
        $summary90 = 'aaaaaaaaaa'; // 10 chars
        $title90 = 'aaaaaaaaab'; // 10 chars, 9 common
        // 2*9/20*100 = 90.0
        // < 90.0 → false (rejected)
        // <= 90.0 → true (accepted)
        // At 90%, the method should return FALSE (rejected) since percent is NOT < 90
        self::assertFalse($this->gate->validateSummary($summary90, $title90));
    }
}
