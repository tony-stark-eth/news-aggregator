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

    public function testUrlCheckShortCircuits(): void
    {
        $repo = $this->createMock(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn($this->createStub(Article::class));
        // Fingerprint and title checks should NOT be called if URL matches
        $repo->expects(self::never())->method('findByFingerprint');
        $repo->expects(self::never())->method('findRecentTitles');

        $service = new DeduplicationService($repo);

        self::assertTrue($service->isDuplicate('https://example.com/existing', 'Title', 'fp123'));
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

    public function testFingerprintCheckShortCircuits(): void
    {
        $repo = $this->createMock(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn(null);
        $repo->method('findByFingerprint')->willReturn($this->createStub(Article::class));
        // Title check should NOT be called if fingerprint matches
        $repo->expects(self::never())->method('findRecentTitles');

        $service = new DeduplicationService($repo);

        self::assertTrue($service->isDuplicate('https://example.com/new', 'Title', 'abc123'));
    }

    public function testNullFingerprintSkipsFingerprintCheck(): void
    {
        $repo = $this->createMock(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn(null);
        // Should NOT check fingerprint when it's null
        $repo->expects(self::never())->method('findByFingerprint');
        $repo->method('findRecentTitles')->willReturn([]);

        $service = new DeduplicationService($repo);

        self::assertFalse($service->isDuplicate('https://example.com/new', 'Unique Title', null));
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

    public function testIsNotDuplicateByDifferentTitle(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'Completely different article about sports',
            ]]),
        );

        self::assertFalse($service->isDuplicate(
            'https://example.com/new',
            'Technology news about quantum computing',
            null,
        ));
    }

    public function testEmptyTitleIsNotDuplicate(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'Some existing article',
            ]]),
        );

        self::assertFalse($service->isDuplicate('https://example.com/new', '', null));
    }

    public function testWhitespaceOnlyTitleIsNotDuplicate(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'Some existing article',
            ]]),
        );

        self::assertFalse($service->isDuplicate('https://example.com/new', '   ', null));
    }

    public function testTitleComparisonIsCaseInsensitive(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'BREAKING NEWS: Major Event',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'breaking news: major event',
            null,
        ));
    }

    public function testChecksUpTo1000RecentTitles(): void
    {
        $repo = $this->createMock(ArticleRepositoryInterface::class);
        $repo->method('findByUrl')->willReturn(null);
        $repo->method('findByFingerprint')->willReturn(null);
        $repo->expects(self::once())
            ->method('findRecentTitles')
            ->with(1000)
            ->willReturn([]);

        $service = new DeduplicationService($repo);

        $service->isDuplicate('https://example.com/new', 'Title', null);
    }

    public function testTrimOnInputDistinguishable(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'x',
            ]]),
        );

        self::assertTrue($service->isDuplicate('https://example.com/new', '  x  ', null));
    }

    public function testTrimOnExistingTitleDistinguishable(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => '  exact match title here  ',
            ]]),
        );

        self::assertTrue($service->isDuplicate('https://example.com/new', 'exact match title here', null));
    }

    public function testMbStrtolowerOnInputWithUmlauts(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'über die wichtigen nachrichten von heute abend',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'ÜBER DIE WICHTIGEN NACHRICHTEN VON HEUTE ABEND',
            null,
        ));
    }

    public function testMbStrtolowerOnExistingWithUmlauts(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'MÜNCHEN STADTRAT BESCHLIESST NEUE MAßNAHMEN HEUTE',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'münchen stadtrat beschliesst neue maßnahmen heute',
            null,
        ));
    }

    public function testSimilarityAt85PercentBoundary(): void
    {
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => 'abcdefghijklmnopqrst',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'abcdefghijklmnopqXXX',
            null,
        ));
    }

    public function testTrimOnExistingTitleKillsUnwrapTrim(): void
    {
        // Stored title has massive whitespace padding.
        // Without trim: mb_strtolower("  hi  ") = "  hi  " compared to "hi" → similar_text gives ~57% (below 85%)
        // With trim: mb_strtolower("hi") = "hi" compared to "hi" → 100% match
        $service = new DeduplicationService(
            $this->buildRepoWithTitles([[
                'title' => '  hi  ',
            ]]),
        );

        self::assertTrue($service->isDuplicate('https://example.com/new', 'hi', null));
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
