<?php

declare(strict_types=1);

namespace App\Article\Twig;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ArticleExtension extends AbstractExtension
{
    private const int WORDS_PER_MINUTE = 200;

    public function __construct(
        private readonly ScoringServiceInterface $scoringService,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('reading_time', $this->readingTime(...)),
        ];
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('score_breakdown', $this->scoreBreakdown(...)),
        ];
    }

    /**
     * Returns estimated reading time in minutes, or null when no text is available.
     */
    public function readingTime(?string $text): ?int
    {
        if ($text === null || $text === '') {
            return null;
        }

        $wordCount = str_word_count($text);

        if ($wordCount === 0) {
            return null;
        }

        return (int) ceil($wordCount / self::WORDS_PER_MINUTE);
    }

    /**
     * Returns score breakdown percentages for tooltip display.
     *
     * @return array{recency: int, category: int, source: int, enrichment: int}|null
     */
    public function scoreBreakdown(Article $article): ?array
    {
        if ($article->getScore() === null) {
            return null;
        }

        $breakdown = $this->scoringService->breakdown($article);

        return [
            'recency' => (int) round($breakdown['recency'] * 100),
            'category' => (int) round($breakdown['category'] * 100),
            'source' => (int) round($breakdown['source'] * 100),
            'enrichment' => (int) round($breakdown['enrichment'] * 100),
        ];
    }
}
