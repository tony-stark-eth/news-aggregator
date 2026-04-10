<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\ValueObject;

use App\Chat\ValueObject\ChatResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatResponse::class)]
final class ChatResponseTest extends TestCase
{
    public function testConstructSetsAllProperties(): void
    {
        $response = new ChatResponse('The answer', [1, 2, 3], 'conv-123');

        self::assertSame('The answer', $response->answer);
        self::assertSame([1, 2, 3], $response->citedArticleIds);
        self::assertSame('conv-123', $response->conversationId);
    }

    public function testConstructWithEmptyCitedIds(): void
    {
        $response = new ChatResponse('No citations', [], 'conv-456');

        self::assertSame([], $response->citedArticleIds);
    }

    public function testConstructWithEmptyAnswer(): void
    {
        $response = new ChatResponse('', [], 'conv-789');

        self::assertSame('', $response->answer);
    }
}
