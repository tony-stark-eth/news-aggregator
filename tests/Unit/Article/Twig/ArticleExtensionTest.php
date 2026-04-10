<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Twig;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Article\Twig\ArticleExtension;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[CoversClass(ArticleExtension::class)]
final class ArticleExtensionTest extends TestCase
{
    /**
     * @var ScoringServiceInterface&MockObject
     */
    private MockObject $scoringService;

    private ArticleExtension $extension;

    protected function setUp(): void
    {
        $this->scoringService = $this->createMock(ScoringServiceInterface::class);
        $this->extension = new ArticleExtension($this->scoringService);
    }

    // --- readingTime ---

    public function testReadingTimeReturnsNullForNullText(): void
    {
        self::assertNull($this->extension->readingTime(null));
    }

    public function testReadingTimeReturnsNullForEmptyString(): void
    {
        self::assertNull($this->extension->readingTime(''));
    }

    public function testReadingTimeReturnsNullForShortExcerpt(): void
    {
        // 10 words (< 100 threshold) -> null — too short for meaningful estimate
        $text = 'one two three four five six seven eight nine ten';
        self::assertNull($this->extension->readingTime($text));
    }

    public function testReadingTimeReturnsNullBelowThreshold(): void
    {
        // 99 words -> null (below 100-word threshold)
        $text = implode(' ', array_fill(0, 99, 'word'));
        self::assertNull($this->extension->readingTime($text));
    }

    public function testReadingTimeExactly100WordsReturnsOneMinute(): void
    {
        // 100 words -> ceil(100/200) = 1 (at threshold)
        $text = implode(' ', array_fill(0, 100, 'word'));
        self::assertSame(1, $this->extension->readingTime($text));
    }

    public function testReadingTimeRoundsUpToNextMinute(): void
    {
        // 201 words -> ceil(201/200) = 2
        $text = implode(' ', array_fill(0, 201, 'word'));
        self::assertSame(2, $this->extension->readingTime($text));
    }

    public function testReadingTimeExactly200WordsReturnsOneMinute(): void
    {
        $text = implode(' ', array_fill(0, 200, 'word'));
        self::assertSame(1, $this->extension->readingTime($text));
    }

    public function testReadingTimeExactly400WordsReturnsTwoMinutes(): void
    {
        $text = implode(' ', array_fill(0, 400, 'word'));
        self::assertSame(2, $this->extension->readingTime($text));
    }

    public function testReadingTimeLargeText(): void
    {
        // 1000 words -> ceil(1000/200) = 5
        $text = implode(' ', array_fill(0, 1000, 'word'));
        self::assertSame(5, $this->extension->readingTime($text));
    }

    public function testReadingTimeWithWhitespaceOnlyReturnsNull(): void
    {
        self::assertNull($this->extension->readingTime('   '));
    }

    // --- scoreBreakdown ---

    public function testScoreBreakdownReturnsNullWhenScoreIsNull(): void
    {
        $article = $this->createArticle();
        // score is null by default

        $this->scoringService->expects(self::never())->method('breakdown');

        self::assertNull($this->extension->scoreBreakdown($article));
    }

    public function testScoreBreakdownReturnsPercentages(): void
    {
        $article = $this->createArticle();
        $article->setScore(0.75);

        $this->scoringService->expects(self::once())
            ->method('breakdown')
            ->with($article)
            ->willReturn([
                'recency' => 0.85,
                'category' => 0.60,
                'source' => 1.0,
                'enrichment' => 0.30,
            ]);

        $result = $this->extension->scoreBreakdown($article);

        self::assertNotNull($result);
        self::assertSame(85, $result['recency']);
        self::assertSame(60, $result['category']);
        self::assertSame(100, $result['source']);
        self::assertSame(30, $result['enrichment']);
    }

    public function testScoreBreakdownRoundsCorrectly(): void
    {
        $article = $this->createArticle();
        $article->setScore(0.5);

        $this->scoringService->expects(self::once())
            ->method('breakdown')
            ->with($article)
            ->willReturn([
                'recency' => 0.555,
                'category' => 0.444,
                'source' => 0.999,
                'enrichment' => 0.001,
            ]);

        $result = $this->extension->scoreBreakdown($article);

        self::assertNotNull($result);
        self::assertSame(56, $result['recency']);
        self::assertSame(44, $result['category']);
        self::assertSame(100, $result['source']);
        self::assertSame(0, $result['enrichment']);
    }

    public function testScoreBreakdownKillsCeilMutant(): void
    {
        $article = $this->createArticle();
        $article->setScore(0.5);

        $this->scoringService->expects(self::once())
            ->method('breakdown')
            ->with($article)
            ->willReturn([
                'recency' => 0.551,
                'category' => 0.552,
                'source' => 0.553,
                'enrichment' => 0.554,
            ]);

        $result = $this->extension->scoreBreakdown($article);

        self::assertNotNull($result);
        // round(55.1)=55, ceil(55.1)=56 — kills ceil mutant
        self::assertSame(55, $result['recency']);
        self::assertSame(55, $result['category']);
        self::assertSame(55, $result['source']);
        self::assertSame(55, $result['enrichment']);
    }

    public function testScoreBreakdownKillsFloorMutant(): void
    {
        $article = $this->createArticle();
        $article->setScore(0.5);

        $this->scoringService->expects(self::once())
            ->method('breakdown')
            ->with($article)
            ->willReturn([
                // x * 100 gives values with fractional >= 0.5
                // round(75.6)=76, floor(75.6)=75 — kills floor mutant on each field
                'recency' => 0.756,
                'category' => 0.757,
                'source' => 0.758,
                'enrichment' => 0.759,
            ]);

        $result = $this->extension->scoreBreakdown($article);

        self::assertNotNull($result);
        self::assertSame(76, $result['recency']);
        self::assertSame(76, $result['category']);
        self::assertSame(76, $result['source']);
        self::assertSame(76, $result['enrichment']);
    }

    // --- Twig registration ---

    public function testRegistersReadingTimeFilter(): void
    {
        $filters = $this->extension->getFilters();
        $filterNames = array_map(static fn (TwigFilter $f): string => $f->getName(), $filters);

        self::assertContains('reading_time', $filterNames);
    }

    public function testRegistersScoreBreakdownFunction(): void
    {
        $functions = $this->extension->getFunctions();
        $functionNames = array_map(static fn (TwigFunction $f): string => $f->getName(), $functions);

        self::assertContains('score_breakdown', $functionNames);
    }

    private function createArticle(): Article
    {
        $category = new Category('Tech', 'tech', 5, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());

        return new Article(
            'Test Article',
            'https://example.com/article/' . random_int(1, 99999),
            $source,
            new \DateTimeImmutable(),
        );
    }
}
