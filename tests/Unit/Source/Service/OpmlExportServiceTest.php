<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\OpmlExportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpmlExportService::class)]
final class OpmlExportServiceTest extends TestCase
{
    private const string FEED_URL = 'https://example.com/feed.xml';

    private const string SITE_URL = 'https://example.com';

    public function testExportEmptySourcesReturnsValidOpml(): void
    {
        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $service = new OpmlExportService($repository);
        $xml = $service->export();

        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<opml version="2.0">', $xml);
        self::assertStringContainsString('<title>News Aggregator Feeds</title>', $xml);
        self::assertStringContainsString('<body/>', $xml);
    }

    public function testExportGroupsSourcesByCategory(): void
    {
        $techCategory = new Category('Tech', 'tech', 8, '#3B82F6');
        $newsCategory = new Category('News', 'news', 10, '#EF4444');

        $source1 = new Source('Ars Technica', 'https://feeds.arstechnica.com/feed', $techCategory, new \DateTimeImmutable());
        $source1->setSiteUrl('https://arstechnica.com');

        $source2 = new Source('The Verge', 'https://theverge.com/feed', $techCategory, new \DateTimeImmutable());

        $source3 = new Source('BBC', 'https://bbc.co.uk/feed', $newsCategory, new \DateTimeImmutable());
        $source3->setSiteUrl('https://bbc.co.uk');

        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn([$source1, $source2, $source3]);

        $service = new OpmlExportService($repository);
        $xml = $service->export();

        // Categories sorted alphabetically: News before Tech
        $newsPos = strpos($xml, 'text="News"');
        $techPos = strpos($xml, 'text="Tech"');
        self::assertIsInt($newsPos);
        self::assertIsInt($techPos);
        self::assertLessThan($techPos, $newsPos);

        // News category contains BBC
        self::assertStringContainsString('text="BBC"', $xml);
        self::assertStringContainsString('xmlUrl="https://bbc.co.uk/feed"', $xml);
        self::assertStringContainsString('htmlUrl="https://bbc.co.uk"', $xml);

        // Tech category contains both feeds
        self::assertStringContainsString('text="Ars Technica"', $xml);
        self::assertStringContainsString('text="The Verge"', $xml);
        self::assertStringContainsString('type="rss"', $xml);
    }

    public function testExportSourceWithoutSiteUrlOmitsHtmlUrl(): void
    {
        $category = new Category('Tech', 'tech', 8, '#3B82F6');
        $source = new Source('Test Feed', self::FEED_URL, $category, new \DateTimeImmutable());

        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn([$source]);

        $service = new OpmlExportService($repository);
        $xml = $service->export();

        self::assertStringContainsString('xmlUrl="' . self::FEED_URL . '"', $xml);
        self::assertStringNotContainsString('htmlUrl', $xml);
    }

    public function testExportSourceIncludesTitleAttribute(): void
    {
        $category = new Category('Tech', 'tech', 8, '#3B82F6');
        $source = new Source('My Feed', self::FEED_URL, $category, new \DateTimeImmutable());
        $source->setSiteUrl(self::SITE_URL);

        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn([$source]);

        $service = new OpmlExportService($repository);
        $xml = $service->export();

        self::assertStringContainsString('title="My Feed"', $xml);
        self::assertStringContainsString('text="My Feed"', $xml);
        self::assertStringContainsString('xmlUrl="' . self::FEED_URL . '"', $xml);
        self::assertStringContainsString('htmlUrl="' . self::SITE_URL . '"', $xml);
    }

    public function testExportReturnsNonEmptyString(): void
    {
        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn([]);

        $service = new OpmlExportService($repository);
        $result = $service->export();

        self::assertNotSame('', $result);
    }
}
