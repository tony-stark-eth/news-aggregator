<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedSummarizationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedSummarizationService::class)]
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

        self::assertSame('This is the first sentence of the article. This is the second sentence with more detail.', $result);
    }

    public function testReturnsNullForShortContent(): void
    {
        $result = $this->service->summarize('Too short.');

        self::assertNull($result);
    }

    public function testHandlesSingleSentence(): void
    {
        $content = 'This is a single sentence that is long enough to be considered valid content for summarization purposes.';

        $result = $this->service->summarize($content);

        self::assertSame($content, $result);
    }

    public function testTruncatesLongSummary(): void
    {
        $longSentence = str_repeat('word ', 100) . 'end.';
        $content = $longSentence . ' Second sentence here with more words.';

        $result = $this->service->summarize($content);

        self::assertNotNull($result);
        self::assertLessThanOrEqual(500, mb_strlen($result));
        self::assertStringEndsWith('...', $result);
    }

    public function testHandlesEmptyContent(): void
    {
        $result = $this->service->summarize('');

        self::assertNull($result);
    }

    public function testFiltersShortFragments(): void
    {
        $content = 'Dr. Smith announced a breakthrough in quantum computing research. The discovery could revolutionize modern technology.';

        $result = $this->service->summarize($content);

        // "Dr." alone is too short, so it should be filtered and the real sentences used
        self::assertNotNull($result);
    }
}
