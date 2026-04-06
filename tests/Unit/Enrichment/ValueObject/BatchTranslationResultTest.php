<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\ValueObject;

use App\Enrichment\ValueObject\BatchTranslationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchTranslationResult::class)]
final class BatchTranslationResultTest extends TestCase
{
    public function testConstructsWithAllFields(): void
    {
        $result = new BatchTranslationResult(
            'Translated title',
            'Translated summary',
            ['Keyword1', 'Keyword2'],
            true,
        );

        self::assertSame('Translated title', $result->title);
        self::assertSame('Translated summary', $result->summary);
        self::assertSame(['Keyword1', 'Keyword2'], $result->keywords);
        self::assertTrue($result->fromAi);
    }

    public function testConstructsWithNullSummary(): void
    {
        $result = new BatchTranslationResult(
            'Title',
            null,
            [],
            false,
        );

        self::assertSame('Title', $result->title);
        self::assertNull($result->summary);
        self::assertSame([], $result->keywords);
        self::assertFalse($result->fromAi);
    }
}
