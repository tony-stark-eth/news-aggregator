<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\ValueObject;

use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Shared\ValueObject\EnrichmentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CombinedEnrichmentResult::class)]
final class CombinedEnrichmentResultTest extends TestCase
{
    public function testConstructsWithAllFields(): void
    {
        $result = new CombinedEnrichmentResult(
            'tech',
            'A summary',
            ['Google', 'AI'],
            EnrichmentMethod::Ai,
            'model-1',
        );

        self::assertSame('tech', $result->categorySlug);
        self::assertSame('A summary', $result->summary);
        self::assertSame(['Google', 'AI'], $result->keywords);
        self::assertSame(EnrichmentMethod::Ai, $result->method);
        self::assertSame('model-1', $result->modelUsed);
    }

    public function testConstructsWithNullFields(): void
    {
        $result = new CombinedEnrichmentResult(
            null,
            null,
            [],
            EnrichmentMethod::RuleBased,
        );

        self::assertNull($result->categorySlug);
        self::assertNull($result->summary);
        self::assertSame([], $result->keywords);
        self::assertSame(EnrichmentMethod::RuleBased, $result->method);
        self::assertNull($result->modelUsed);
    }
}
