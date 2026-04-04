<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\ValueObject;

use App\Article\ValueObject\ArticleFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleFingerprint::class)]
final class ArticleFingerprintTest extends TestCase
{
    public function testFromContentNormalizesWhitespace(): void
    {
        $fp1 = ArticleFingerprint::fromContent("hello  world\n\tfoo");
        $fp2 = ArticleFingerprint::fromContent('hello world foo');

        self::assertTrue($fp1->equals($fp2));
    }

    public function testFromContentIsCaseInsensitive(): void
    {
        $fp1 = ArticleFingerprint::fromContent('Hello World');
        $fp2 = ArticleFingerprint::fromContent('hello world');

        self::assertTrue($fp1->equals($fp2));
    }

    public function testDifferentContentProducesDifferentFingerprints(): void
    {
        $fp1 = ArticleFingerprint::fromContent('article one');
        $fp2 = ArticleFingerprint::fromContent('article two');

        self::assertFalse($fp1->equals($fp2));
    }

    public function testToString(): void
    {
        $fp = ArticleFingerprint::fromContent('test');

        self::assertNotEmpty((string) $fp);
    }
}
