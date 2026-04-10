<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\ValueObject;

use App\Chat\ValueObject\AnswerCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnswerCollector::class)]
final class AnswerCollectorTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $collector = new AnswerCollector();

        self::assertSame('', $collector->getText());
    }

    public function testAppendAccumulatesText(): void
    {
        $collector = new AnswerCollector();
        $collector->append('Hello ');
        $collector->append('world');

        self::assertSame('Hello world', $collector->getText());
    }

    public function testAppendEmptyString(): void
    {
        $collector = new AnswerCollector();
        $collector->append('');

        self::assertSame('', $collector->getText());
    }

    public function testAppendMultibyteCharacters(): void
    {
        $collector = new AnswerCollector();
        $collector->append('Hallo ');
        $collector->append('uber');

        self::assertSame('Hallo uber', $collector->getText());
    }
}
