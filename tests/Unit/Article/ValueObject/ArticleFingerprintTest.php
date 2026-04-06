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

    public function testMbStrtolowerWithUmlauts(): void
    {
        // mb_strtolower("ÜBER") = "über", strtolower("ÜBER") would not convert Ü
        $fp1 = ArticleFingerprint::fromContent('ÜBER DIE NACHRICHTEN');
        $fp2 = ArticleFingerprint::fromContent('über die nachrichten');

        self::assertTrue($fp1->equals($fp2));
    }

    public function testTrimApplied(): void
    {
        // Trim removes leading/trailing whitespace before normalization
        $fp1 = ArticleFingerprint::fromContent('  hello world  ');
        $fp2 = ArticleFingerprint::fromContent('hello world');

        self::assertTrue($fp1->equals($fp2));
    }

    public function testTrimRequiredForCorrectFingerprint(): void
    {
        // If trim is removed, leading/trailing whitespace would affect the hash
        // " hello " != "hello" without trim
        $fp1 = ArticleFingerprint::fromContent('  content here  ');
        $fpNoSpace = ArticleFingerprint::fromContent('content here');

        // These should be equal because trim + normalize removes surrounding spaces
        self::assertTrue($fp1->equals($fpNoSpace));
    }
}
