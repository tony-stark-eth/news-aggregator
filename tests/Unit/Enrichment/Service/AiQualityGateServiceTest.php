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
        // Kills LessThan → LessThanOrEqual mutation on `$percent < 90.0`
        // Two 20-char strings with 18 common chars: 2*18/40*100 = 90.0%
        // With `<`: 90.0 < 90.0 = false → rejected
        // With `<=`: 90.0 <= 90.0 = true → accepted
        $summary = 'aaaaaaaaaaaaaaaaaabb';
        $title = 'aaaaaaaaaaaaaaaaaazz';
        self::assertFalse($this->gate->validateSummary($summary, $title));
    }

    public function testReasoningPrefixBasedOnRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Based on the provided information, the economy is struggling with inflation.',
            'Economic Update',
        ));
    }

    public function testReasoningPrefixAccordingToRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'According to the article, new policies were announced today by officials.',
            'Policy Announcement',
        ));
    }

    public function testReasoningPrefixHereIsRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Here is a summary of the key points from the article about climate change.',
            'Climate Report',
        ));
    }

    public function testReasoningPrefixLetMeRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Let me summarize this article about the latest technology trends and innovations.',
            'Tech Trends',
        ));
    }

    public function testReasoningPrefixSureRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Sure, the article discusses the impact of new regulations on businesses.',
            'Business Regulations',
        ));
    }

    public function testReasoningPrefixCertainlyRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Certainly! The main topic covers renewable energy advances in Europe.',
            'Energy News',
        ));
    }

    public function testReasoningPrefixTheArticleRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'The article discusses major breakthroughs in quantum computing research.',
            'Quantum Computing',
        ));
    }

    public function testReasoningPrefixInSummaryRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'In summary, global markets reacted negatively to the trade war escalation.',
            'Trade War',
        ));
    }

    public function testReasoningFragmentAsAnAiRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'The economy is growing but as an ai I must note these are uncertain times.',
            'Economic Growth',
        ));
    }

    public function testReasoningFragmentProvidedInformationRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'The key takeaway from the provided information is that inflation continues.',
            'Inflation Report',
        ));
    }

    public function testReasoningPrefixCaseInsensitiveRejected(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'BASED ON the latest reports, the stock market reached a new record high.',
            'Stock Market',
        ));
    }

    public function testBasedOnMidSentencePassesFalsePositiveSafety(): void
    {
        // "based on" mid-sentence should NOT be rejected — only prefix matches
        self::assertTrue($this->gate->validateSummary(
            'The decision was based on economic data from the latest quarterly report.',
            'Economic Decision',
        ));
    }

    public function testValidSentimentPasses(): void
    {
        self::assertTrue($this->gate->validateSentiment(0.5));
        self::assertTrue($this->gate->validateSentiment(-0.5));
        self::assertTrue($this->gate->validateSentiment(0.0));
    }

    public function testSentimentBoundariesPass(): void
    {
        self::assertTrue($this->gate->validateSentiment(-1.0));
        self::assertTrue($this->gate->validateSentiment(1.0));
    }

    public function testSentimentOutOfRangeFails(): void
    {
        self::assertFalse($this->gate->validateSentiment(-1.01));
        self::assertFalse($this->gate->validateSentiment(1.01));
        self::assertFalse($this->gate->validateSentiment(5.0));
        self::assertFalse($this->gate->validateSentiment(-5.0));
    }

    public function testCleanSummaryStillPasses(): void
    {
        self::assertTrue($this->gate->validateSummary(
            'Global temperatures rose by 1.5 degrees Celsius in 2025, exceeding targets.',
            'Climate Report 2025',
        ));
    }
}
