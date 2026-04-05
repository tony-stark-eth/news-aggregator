<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\DeduplicationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeduplicationService::class)]
final class DeduplicationServiceTest extends TestCase
{
    public function testIsDuplicateByUrl(): void
    {
        $article = $this->createStub(Article::class);

        $repo = $this->createStub(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn($article);

        $service = new DeduplicationService($repo);

        self::assertTrue($service->isDuplicate('https://example.com/existing', 'Any Title', null));
    }

    public function testIsNotDuplicateForNewUrl(): void
    {
        $service = new DeduplicationService($this->buildRepoWithTitles([]));

        self::assertFalse($service->isDuplicate('https://example.com/new', 'Unique Title', null));
    }

    public function testIsDuplicateByFingerprint(): void
    {
        $repo = $this->createStub(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn(null);
        $repo->method('findByFingerprint')->willReturn($this->createStub(Article::class));
        $repo->method('findRecentTitles')->willReturn([]);

        $service = new DeduplicationService($repo);

        self::assertTrue($service->isDuplicate('https://example.com/new', 'Title', 'abc123'));
    }

    public function testIsDuplicateBySimilarTitle(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'Breaking: Major Event Happens Today',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'Breaking: Major Event Happens Today!',
            null,
        ));
    }

    /**
     * @param list<array{title: string}> $titles
     */
    private function buildRepoWithTitles(array $titles): ArticleRepositoryInterface
    {
        $repo = $this->createStub(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn(null);
        $repo->method('findByFingerprint')->willReturn(null);
        $repo->method('findRecentTitles')->willReturn($titles);

        return $repo;
    }
}
