<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedCategorizationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedCategorizationService::class)]
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

        self::assertSame('tech', $result);
    }

    public function testCategorizePoliticsArticle(): void
    {
        $result = $this->service->categorize(
            'Parliament votes on new election law',
            'The government coalition passed the new policy with support from the opposition.',
        );

        self::assertSame('politics', $result);
    }

    public function testCategorizeSportsArticle(): void
    {
        $result = $this->service->categorize(
            'Bundesliga: Bayern wins championship match',
            'The team celebrated their league victory at the stadium after defeating the coach.',
        );

        self::assertSame('sports', $result);
    }

    public function testReturnsNullForAmbiguousContent(): void
    {
        $result = $this->service->categorize(
            'Something happened today',
            'This is a generic article without clear category keywords.',
        );

        self::assertNull($result);
    }

    public function testUsesContentForCategorization(): void
    {
        // Title alone has no keywords, but content does
        $result = $this->service->categorize(
            'Breaking news from the lab',
            'Scientists made a major discovery in quantum physics research with a new experiment.',
        );

        self::assertSame('science', $result);
    }
}
