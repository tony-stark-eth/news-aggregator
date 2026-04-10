<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\ValueObject;

use App\Chat\ValueObject\StreamContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversClass(StreamContext::class)]
final class StreamContextTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $history = new MessageBag();
        $articles = [
            [
                'id' => 1,
                'title' => 'Test Article',
                'summary' => 'Summary',
                'url' => 'https://example.com',
            ],
        ];

        $ctx = new StreamContext('conv-1', 'Hello', $history, $articles);

        self::assertSame('conv-1', $ctx->conversationId);
        self::assertSame('Hello', $ctx->userMessage);
        self::assertSame($history, $ctx->history);
        self::assertSame($articles, $ctx->articles);
    }

    public function testEmptyArticles(): void
    {
        $ctx = new StreamContext('conv-2', 'Query', new MessageBag(), []);

        self::assertSame([], $ctx->articles);
    }
}
