<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\AI\Platform;

use App\Shared\AI\Platform\OpenAiCompatibleUrlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenAiCompatibleUrlNormalizer::class)]
final class OpenAiCompatibleUrlNormalizerTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, OpenAiCompatibleUrlNormalizer::normalize($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'strips trailing slash' => ['http://vllm.local/v1/', 'http://vllm.local'];
        yield 'strips /v1 suffix' => ['http://192.168.30.121:8000/v1', 'http://192.168.30.121:8000'];
        yield 'keeps host without v1' => ['http://192.168.30.121:8000', 'http://192.168.30.121:8000'];
    }
}
