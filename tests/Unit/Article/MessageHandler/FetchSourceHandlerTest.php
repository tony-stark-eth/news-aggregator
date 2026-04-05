<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\MessageHandler\FetchSourceHandler;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\DeduplicationServiceInterface;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\KeywordExtractionServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Enrichment\Service\TranslationServiceInterface;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Notification\Service\ArticleMatcherServiceInterface;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use App\Source\Exception\FeedFetchException;
use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\FeedFetcherServiceInterface;
use App\Source\Service\FeedItem;
use App\Source\Service\FeedItemCollection;
use App\Source\Service\FeedParserServiceInterface;
use App\Source\ValueObject\SourceHealth;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(FetchSourceHandler::class)]
final class FetchSourceHandlerTest extends TestCase
{
    /**
     * @var ArticleRepositoryInterface&MockObject
     */
    private MockObject $articleRepository;

    /**
     * @var SourceRepositoryInterface&MockObject
     */
    private MockObject $sourceRepository;

    /**
     * @var FeedFetcherServiceInterface&MockObject
     */
    private MockObject $fetcher;

    /**
     * @var FeedParserServiceInterface&MockObject
     */
    private MockObject $parser;

    private MockClock $clock;

    private FetchSourceHandler $handler;

    private Source $source;

    protected function setUp(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $this->source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->sourceRepository->method('findById')->willReturn($this->source);

        $this->fetcher = $this->createMock(FeedFetcherServiceInterface::class);
        $this->parser = $this->createMock(FeedParserServiceInterface::class);
        $this->clock = new MockClock('2026-04-04 12:00:00');

        $dedup = $this->createStub(DeduplicationServiceInterface::class);
        $dedup->method('isDuplicate')->willReturn(false);

        $categorization = $this->createStub(CategorizationServiceInterface::class);
        $categorization->method('categorize')->willReturn(new EnrichmentResult(null, EnrichmentMethod::RuleBased));

        $summarization = $this->createStub(SummarizationServiceInterface::class);
        $summarization->method('summarize')->willReturn(new EnrichmentResult('A test summary.', EnrichmentMethod::RuleBased));

        $keywordExtraction = $this->createStub(KeywordExtractionServiceInterface::class);
        $keywordExtraction->method('extract')->willReturn(['Test', 'Keyword']);

        $translation = $this->createStub(TranslationServiceInterface::class);
        $translation->method('translate')->willReturnArgument(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $this->handler = new FetchSourceHandler(
            $this->articleRepository,
            $this->sourceRepository,
            $this->createStub(CategoryRepositoryInterface::class),
            $em,
            $this->fetcher,
            $this->parser,
            $dedup,
            $categorization,
            $summarization,
            $translation,
            $keywordExtraction,
            $this->createStub(ScoringServiceInterface::class),
            $this->createStub(ArticleMatcherServiceInterface::class),
            $this->createStub(MessageBusInterface::class),
            $this->clock,
            new NullLogger(),
            'en',
        );
    }

    public function testHandlesFetchAndPersistsArticles(): void
    {
        $this->fetcher->method('fetch')->willReturn('<rss>...</rss>');
        $this->parser->method('parse')->willReturn(new FeedItemCollection([
            new FeedItem('Article 1', 'https://example.com/1', '<p>Content</p>', 'Content text here for summarization', null),
            new FeedItem('Article 2', 'https://example.com/2', null, null, null),
        ]));

        $saved = [];
        $this->articleRepository->method('save')->willReturnCallback(function (Article $article) use (&$saved): void {
            $saved[] = $article;
        });

        ($this->handler)(new FetchSourceMessage(1));

        self::assertCount(2, $saved);
        self::assertSame('Article 1', $saved[0]->getTitle());
        self::assertSame(SourceHealth::Healthy, $this->source->getHealthStatus());
        // First article has content, so it gets rule-based enrichment
        self::assertSame(EnrichmentMethod::RuleBased, $saved[0]->getEnrichmentMethod());
        self::assertSame('A test summary.', $saved[0]->getSummary());
    }

    public function testRecordsFailureOnFetchError(): void
    {
        $this->fetcher->method('fetch')->willThrowException(
            FeedFetchException::fromUrl('https://example.com/feed', 'timeout'),
        );

        ($this->handler)(new FetchSourceMessage(1));

        self::assertSame(SourceHealth::Degraded, $this->source->getHealthStatus());
        self::assertSame(1, $this->source->getErrorCount());
    }

    public function testSkipsDisabledSource(): void
    {
        $this->source->setEnabled(false);

        $this->fetcher->expects(self::never())->method('fetch');

        ($this->handler)(new FetchSourceMessage(1));

        self::assertFalse($this->source->isEnabled());
    }
}
