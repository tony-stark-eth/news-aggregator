<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedSummarizationService;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedSummarizationService::class)]
#[UsesClass(EnrichmentResult::class)]
final class RuleBasedSummarizationServiceTest extends TestCase
{
    private RuleBasedSummarizationService $service;

    protected function setUp(): void
    {
        $this->service = new RuleBasedSummarizationService();
    }

    public function testExtractsFirstTwoSentences(): void
    {
        $content = 'This is the first sentence of the article. This is the second sentence with more detail. This is the third sentence that should not be included.';

        $result = $this->service->summarize($content);

        self::assertSame('This is the first sentence of the article. This is the second sentence with more detail.', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }

    public function testReturnsNullValueForShortContent(): void
    {
        $result = $this->service->summarize('Too short.');

        self::assertNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testReturnsNullForContentExactlyAtMinLength(): void
    {
        // MIN_CONTENT_LENGTH = 50; create content of exactly 49 chars
        $content = str_repeat('x', 49);
        $result = $this->service->summarize($content);

        self::assertNull($result->value);
    }

    public function testAcceptsContentAtMinLength(): void
    {
        // 50 chars + sentence structure
        $content = 'This is a sentence long enough to be over fifty characters for valid content.';
        $result = $this->service->summarize($content);

        self::assertNotNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testHandlesSingleSentence(): void
    {
        $content = 'This is a single sentence that is long enough to be considered valid content for summarization purposes.';

        $result = $this->service->summarize($content);

        self::assertSame($content, $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testTruncatesLongSummary(): void
    {
        $longSentence = str_repeat('word ', 100) . 'end.';
        $content = $longSentence . ' Second sentence here with more words.';

        $result = $this->service->summarize($content);

        self::assertNotNull($result->value);
        self::assertLessThanOrEqual(500, mb_strlen($result->value));
        self::assertStringEndsWith('...', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testTruncatedSummaryExactly500Chars(): void
    {
        $longSentence = str_repeat('word ', 100) . 'end.';
        $content = $longSentence . ' Second sentence here with more words to ensure truncation happens.';

        $result = $this->service->summarize($content);

        self::assertNotNull($result->value);
        // MAX_SUMMARY_LENGTH = 500, truncated to 497 + "..." = 500
        self::assertSame(500, mb_strlen($result->value));
    }

    public function testHandlesEmptyContent(): void
    {
        $result = $this->service->summarize('');

        self::assertNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testHandlesWhitespaceOnlyContent(): void
    {
        $result = $this->service->summarize('   ');

        self::assertNull($result->value);
    }

    public function testFiltersShortFragments(): void
    {
        $content = 'Dr. Smith announced a breakthrough in quantum computing research. The discovery could revolutionize modern technology.';

        $result = $this->service->summarize($content);

        self::assertNotNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        // "Dr." alone (<10 chars) should be filtered; real sentences should be used
        self::assertStringContainsString('announced a breakthrough', $result->value);
    }

    public function testReturnsNullWhenAllSentencesAreTooShort(): void
    {
        // All fragments < 10 chars after split, but total content >= 50
        $content = str_repeat('Ok. Ab. ', 10);

        $result = $this->service->summarize($content);

        // All split parts are too short -> filtered to empty -> return null
        self::assertNull($result->value);
    }

    public function testHandlesExclamationAndQuestionMarks(): void
    {
        $content = 'What an amazing discovery! The scientists were ecstatic about the results. How will this change everything?';

        $result = $this->service->summarize($content);

        self::assertNotNull($result->value);
        // Should take first two valid sentences
        self::assertStringContainsString('What an amazing discovery!', $result->value);
        self::assertStringContainsString('The scientists were ecstatic', $result->value);
    }

    public function testTitleParameterAccepted(): void
    {
        $content = 'This is a sufficiently long article content for testing the title parameter functionality.';

        $result = $this->service->summarize($content, 'My Title');

        self::assertNotNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }
}
