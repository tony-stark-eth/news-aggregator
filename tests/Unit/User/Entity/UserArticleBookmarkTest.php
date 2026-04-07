<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Entity;

use App\Article\Entity\Article;
use App\Source\Entity\Source;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserArticleBookmark::class)]
final class UserArticleBookmarkTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $user = new User('test@example.com', 'hashed');
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/test', $source, new \DateTimeImmutable('2026-04-07'));
        $createdAt = new \DateTimeImmutable('2026-04-07 12:00:00');

        $bookmark = new UserArticleBookmark($user, $article, $createdAt);

        self::assertNull($bookmark->getId());
        self::assertSame($user, $bookmark->getUser());
        self::assertSame($article, $bookmark->getArticle());
        self::assertSame($createdAt, $bookmark->getCreatedAt());
    }
}
