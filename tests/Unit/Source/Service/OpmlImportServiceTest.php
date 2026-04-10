<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Dto\OpmlImportResultDto;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\OpmlImportService;
use App\Source\ValueObject\FeedPreview;
use App\Source\ValueObject\FeedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(OpmlImportService::class)]
#[CoversClass(OpmlImportResultDto::class)]
#[CoversClass(FeedPreview::class)]
final class OpmlImportServiceTest extends TestCase
{
    private const string VALID_OPML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <opml version="2.0">
          <head><title>Test Feeds</title></head>
          <body>
            <outline text="Tech" title="Tech">
              <outline type="rss" text="Ars Technica" title="Ars Technica"
                       xmlUrl="https://feeds.arstechnica.com/feed"
                       htmlUrl="https://arstechnica.com"/>
              <outline type="rss" text="The Verge" title="The Verge"
                       xmlUrl="https://theverge.com/feed"/>
            </outline>
            <outline text="News" title="News">
              <outline type="rss" text="BBC" title="BBC"
                       xmlUrl="https://bbc.co.uk/feed"
                       htmlUrl="https://bbc.co.uk"/>
            </outline>
          </body>
        </opml>
        XML;

    private const string FLAT_OPML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <opml version="2.0">
          <head><title>Flat Feeds</title></head>
          <body>
            <outline type="rss" text="Flat Feed" xmlUrl="https://example.com/flat.xml" htmlUrl="https://example.com"/>
          </body>
        </opml>
        XML;

    private MockObject&SourceRepositoryInterface $sourceRepository;

    private MockObject&CategoryRepositoryInterface $categoryRepository;

    private MockClock $clock;

    private OpmlImportService $service;

    protected function setUp(): void
    {
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-01-15 10:00:00'));

        $this->service = new OpmlImportService(
            $this->sourceRepository,
            $this->categoryRepository,
            $this->clock,
        );
    }

    public function testImportCreatesSourcesFromGroupedOpml(): void
    {
        $techCategory = new Category('Tech', 'tech', 8, '#3B82F6');
        $newsCategory = new Category('News', 'news', 10, '#EF4444');

        $this->sourceRepository->expects(self::exactly(3))
            ->method('findByFeedUrl')
            ->willReturn(null);

        $this->sourceRepository->expects(self::exactly(3))
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $this->categoryRepository->expects(self::exactly(2))
            ->method('findBySlug')
            ->willReturnCallback(static fn (string $slug): ?Category => match ($slug) {
                'tech' => $techCategory,
                'news' => $newsCategory,
                default => null,
            });

        $result = $this->service->import(self::VALID_OPML);

        self::assertSame(3, $result->importedCount);
        self::assertSame(0, $result->skippedCount);
        self::assertSame(['Ars Technica', 'The Verge', 'BBC'], $result->importedNames);
        self::assertSame([], $result->skippedNames);
    }

    public function testImportSkipsDuplicateFeedUrls(): void
    {
        $techCategory = new Category('Tech', 'tech', 8, '#3B82F6');
        $existingSource = new Source('Existing', 'https://feeds.arstechnica.com/feed', $techCategory, new \DateTimeImmutable());

        $this->sourceRepository->expects(self::exactly(3))
            ->method('findByFeedUrl')
            ->willReturnCallback(static fn (string $url): ?Source => match ($url) {
                'https://feeds.arstechnica.com/feed' => $existingSource,
                default => null,
            });

        $newsCategory = new Category('News', 'news', 10, '#EF4444');
        $this->categoryRepository->method('findBySlug')
            ->willReturnCallback(static fn (string $slug): ?Category => match ($slug) {
                'tech' => $techCategory,
                'news' => $newsCategory,
                default => null,
            });

        $this->sourceRepository->expects(self::exactly(2))
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $result = $this->service->import(self::VALID_OPML);

        self::assertSame(2, $result->importedCount);
        self::assertSame(1, $result->skippedCount);
        self::assertSame(['The Verge', 'BBC'], $result->importedNames);
        self::assertSame(['Ars Technica'], $result->skippedNames);
    }

    public function testImportHandlesFlatOutlinesWithoutCategories(): void
    {
        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');

        $this->sourceRepository->expects(self::once())
            ->method('findByFeedUrl')
            ->with('https://example.com/flat.xml')
            ->willReturn(null);

        $this->sourceRepository->expects(self::once())
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('uncategorized')
            ->willReturn($uncategorized);

        $result = $this->service->import(self::FLAT_OPML);

        self::assertSame(1, $result->importedCount);
        self::assertSame(0, $result->skippedCount);
        self::assertSame(['Flat Feed'], $result->importedNames);
    }

    public function testImportCreatesNewCategoryWhenNotFound(): void
    {
        $this->sourceRepository->expects(self::once())
            ->method('findByFeedUrl')
            ->willReturn(null);

        $this->sourceRepository->expects(self::once())
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('uncategorized')
            ->willReturn(null);

        $this->categoryRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Category $cat): bool => $cat->getName() === 'Uncategorized'
                    && $cat->getSlug() === 'uncategorized'
                    && $cat->getColor() === '#6B7280'
                    && $cat->getWeight() === 5),
                true,
            );

        $result = $this->service->import(self::FLAT_OPML);

        self::assertSame(1, $result->importedCount);
    }

    public function testImportThrowsOnInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OPML: could not parse XML.');

        $this->service->import('not valid xml <<>>');
    }

    public function testImportReturnsZeroCountsForEmptyBody(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Empty</title></head>
              <body></body>
            </opml>
            XML;

        $this->sourceRepository->expects(self::never())
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(0, $result->importedCount);
        self::assertSame(0, $result->skippedCount);
        self::assertSame([], $result->importedNames);
        self::assertSame([], $result->skippedNames);
    }

    public function testImportUsesXmlUrlAsFallbackName(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" xmlUrl="https://example.com/no-name.xml"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);

        $this->sourceRepository->expects(self::once())
            ->method('findByFeedUrl')
            ->with('https://example.com/no-name.xml')
            ->willReturn(null);

        $this->sourceRepository->expects(self::once())
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(1, $result->importedCount);
        self::assertSame(['https://example.com/no-name.xml'], $result->importedNames);
    }

    public function testImportHandlesEmptyHtmlUrl(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" text="Test" xmlUrl="https://example.com/feed.xml" htmlUrl=""/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);

        $savedSource = null;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Source $source) use (&$savedSource): bool {
                $savedSource = $source;

                return true;
            }));

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(1, $result->importedCount);
        self::assertInstanceOf(Source::class, $savedSource);
        self::assertNull($savedSource->getSiteUrl());
    }

    public function testImportSkipsChildOutlinesWithoutXmlUrl(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="Category">
                  <outline text="No URL here"/>
                  <outline type="rss" text="Valid" xmlUrl="https://example.com/valid.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $category = new Category('Category', 'category', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($category);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);

        $this->sourceRepository->expects(self::once())
            ->method('save');

        $this->sourceRepository->expects(self::once())
            ->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(1, $result->importedCount);
        self::assertSame(['Valid'], $result->importedNames);
    }

    public function testImportGeneratesSlugFromCategoryName(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="Science &amp; Nature">
                  <outline type="rss" text="Feed" xmlUrl="https://example.com/feed.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('science-nature')
            ->willReturn(null);

        $this->categoryRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Category $cat): bool => $cat->getSlug() === 'science-nature'
                    && $cat->getName() === 'Science & Nature'),
                true,
            );

        $this->service->import($opml);
    }

    public function testImportHandlesMultibyteCategoryName(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="Ubersicht">
                  <outline type="rss" text="Feed" xmlUrl="https://example.com/feed.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('ubersicht')
            ->willReturn(null);

        $this->categoryRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Category $cat): bool => $cat->getSlug() === 'ubersicht'),
                true,
            );

        $this->service->import($opml);
    }

    public function testImportSetsSiteUrlFromHtmlUrl(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" text="My Feed" xmlUrl="https://example.com/feed.xml" htmlUrl="https://example.com"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);

        $savedSource = null;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Source $source) use (&$savedSource): bool {
                $savedSource = $source;

                return true;
            }));
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->service->import($opml);

        self::assertInstanceOf(Source::class, $savedSource);
        self::assertSame('https://example.com', $savedSource->getSiteUrl());
    }

    public function testImportDoesNotSetSiteUrlWhenHtmlUrlIsNull(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" text="No Site" xmlUrl="https://example.com/feed.xml"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);

        $savedSource = null;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Source $source) use (&$savedSource): bool {
                $savedSource = $source;

                return true;
            }));
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->service->import($opml);

        self::assertInstanceOf(Source::class, $savedSource);
        self::assertNull($savedSource->getSiteUrl());
    }

    public function testImportHandlesOutlineWithXmlUrlButNoType(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="No Type" xmlUrl="https://example.com/notype.xml"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(1, $result->importedCount);
        self::assertSame(['No Type'], $result->importedNames);
    }

    public function testImportHandlesMultipleFlatRssOutlines(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" text="Feed A" xmlUrl="https://example.com/a.xml"/>
                <outline type="rss" text="Feed B" xmlUrl="https://example.com/b.xml"/>
                <outline type="rss" text="Feed C" xmlUrl="https://example.com/c.xml"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::exactly(3))->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(3, $result->importedCount);
        self::assertSame(['Feed A', 'Feed B', 'Feed C'], $result->importedNames);
    }

    public function testImportUsesTitleWhenTextIsMissingForCategory(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline title="Title Only Category">
                  <outline type="rss" text="Feed" xmlUrl="https://example.com/feed.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('title-only-category')
            ->willReturn(null);

        $this->categoryRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Category $cat): bool => $cat->getName() === 'Title Only Category'),
                true,
            );

        $this->service->import($opml);
    }

    public function testImportUsesTitleWhenTextIsMissingForOutline(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline type="rss" title="Title Feed" xmlUrl="https://example.com/feed.xml"/>
              </body>
            </opml>
            XML;

        $uncategorized = new Category('Uncategorized', 'uncategorized', 5, '#6B7280');
        $this->categoryRepository->method('findBySlug')->willReturn($uncategorized);
        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $result = $this->service->import($opml);

        self::assertSame(['Title Feed'], $result->importedNames);
    }

    public function testImportResolvesExistingCategoryFromDatabaseOnlyOnce(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="Tech">
                  <outline type="rss" text="Feed A" xmlUrl="https://example.com/a.xml"/>
                  <outline type="rss" text="Feed B" xmlUrl="https://example.com/b.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $techCategory = new Category('Tech', 'tech', 8, '#3B82F6');

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::exactly(2))->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        // Database lookup should happen only once -- second feed reuses the cache
        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('tech')
            ->willReturn($techCategory);

        $result = $this->service->import($opml);

        self::assertSame(2, $result->importedCount);
    }

    public function testImportSlugTrimsLeadingAndTrailingHyphens(): void
    {
        $opml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <opml version="2.0">
              <head><title>Test</title></head>
              <body>
                <outline text="--Dashes--">
                  <outline type="rss" text="Feed" xmlUrl="https://example.com/feed.xml"/>
                </outline>
              </body>
            </opml>
            XML;

        $this->sourceRepository->method('findByFeedUrl')->willReturn(null);
        $this->sourceRepository->expects(self::once())->method('save');
        $this->sourceRepository->expects(self::once())->method('flush');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('dashes')
            ->willReturn(null);

        $this->categoryRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (Category $cat): bool => $cat->getSlug() === 'dashes'),
                true,
            );

        $this->service->import($opml);
    }

    public function testImportDefaultsHasFullContentToFalse(): void
    {
        // FeedPreview default value -- tested here for mutation coverage
        $feedUrl = new FeedUrl('https://example.com/feed.xml');
        $preview = new FeedPreview(
            title: 'Test',
            itemCount: 5,
            detectedLanguage: null,
            feedUrl: $feedUrl,
        );

        self::assertFalse($preview->hasFullContent);
    }
}
