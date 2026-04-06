<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedCategorizationService;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedCategorizationService::class)]
#[UsesClass(EnrichmentResult::class)]
final class RuleBasedCategorizationServiceTest extends TestCase
{
    private RuleBasedCategorizationService $service;

    protected function setUp(): void
    {
        $this->service = new RuleBasedCategorizationService();
    }

    public function testCategorizeTechArticle(): void
    {
        $result = $this->service->categorize(
            'Google announces new AI model for developers',
            'The new artificial intelligence model improves developer productivity with better API integration.',
        );

        self::assertSame('tech', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }

    public function testCategorizePoliticsArticle(): void
    {
        $result = $this->service->categorize(
            'Parliament votes on new election law',
            'The government coalition passed the new policy with support from the opposition.',
        );

        self::assertSame('politics', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testCategorizeBusinessArticle(): void
    {
        $result = $this->service->categorize(
            'Stock market reaches new high',
            'The economy showed strong growth with rising revenue and profit across the finance sector.',
        );

        self::assertSame('business', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testCategorizeScienceArticle(): void
    {
        $result = $this->service->categorize(
            'Scientists make quantum physics breakthrough',
            'The research team published their discovery from a new experiment in the lab.',
        );

        self::assertSame('science', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testCategorizeSportsArticle(): void
    {
        $result = $this->service->categorize(
            'Bundesliga: Bayern wins championship match',
            'The team celebrated their league victory at the stadium after defeating the coach.',
        );

        self::assertSame('sports', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testReturnsNullValueForAmbiguousContent(): void
    {
        $result = $this->service->categorize(
            'Something happened today',
            'This is a generic article without clear category keywords.',
        );

        self::assertNull($result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testRequiresAtLeastTwoKeywordMatches(): void
    {
        // Only one keyword match ("government") -> should return null
        $result = $this->service->categorize(
            'Random topic',
            'The government did something.',
        );

        self::assertNull($result->value);
    }

    public function testUsesContentForCategorization(): void
    {
        // Title alone has no keywords, but content does
        $result = $this->service->categorize(
            'Breaking news from the lab',
            'Scientists made a major discovery in quantum physics research with a new experiment.',
        );

        self::assertSame('science', $result->value);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
    }

    public function testIsCaseInsensitive(): void
    {
        $result = $this->service->categorize(
            'PARLIAMENT VOTES ON ELECTION',
            'THE GOVERNMENT PASSED A NEW LAW WITH MINISTER SUPPORT.',
        );

        self::assertSame('politics', $result->value);
    }

    public function testHandlesNullContent(): void
    {
        $result = $this->service->categorize(
            'Parliament votes on new election law with government coalition',
            null,
        );

        self::assertSame('politics', $result->value);
    }

    public function testBestCategoryWins(): void
    {
        // Mix of tech and politics keywords - tech should win with more matches
        $result = $this->service->categorize(
            'Google AI developer API cloud software announcement',
            'The new artificial intelligence programming platform.',
        );

        self::assertSame('tech', $result->value);
    }

    public function testCategorizeWithGermanUmlautContent(): void
    {
        // German text with umlauts - tests mb_strtolower vs strtolower
        $result = $this->service->categorize(
            'Bundesliga: MÜNCHEN gewinnt Meisterschaft',
            'Das team feiert den league sieg am stadium nach dem match gegen den trainer.',
        );

        self::assertSame('sports', $result->value);
    }

    public function testCategorizeWithAccentedChars(): void
    {
        $result = $this->service->categorize(
            'Élection présidentielle: résultats du vote',
            'Le government a annoncé une nouvelle policy après election.',
        );

        self::assertSame('politics', $result->value);
    }
}
