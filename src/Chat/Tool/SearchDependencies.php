<?php

declare(strict_types=1);

namespace App\Chat\Tool;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Repository\VectorSearchRepositoryInterface;
use App\Chat\Service\ArticleContextFormatterInterface;
use App\Chat\Service\EmbeddingServiceInterface;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Psr\Clock\ClockInterface;

/**
 * Bundles dependencies for ArticleSearchTool to stay within the 5-param constructor limit.
 */
final readonly class SearchDependencies
{
    public function __construct(
        public EmbeddingServiceInterface $embedding,
        public VectorSearchRepositoryInterface $vectorSearch,
        public ArticleSearchServiceInterface $keywordSearch,
        public ArticleRepositoryInterface $articleRepository,
        public ArticleContextFormatterInterface $formatter,
        public ClockInterface $clock,
    ) {
    }
}
