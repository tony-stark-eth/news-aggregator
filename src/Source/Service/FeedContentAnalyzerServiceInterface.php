<?php

declare(strict_types=1);

namespace App\Source\Service;

interface FeedContentAnalyzerServiceInterface
{
    /**
     * Analyzes feed items to determine if the feed provides full content.
     *
     * @param FeedItemCollection $items the parsed feed items to analyze
     */
    public function hasFullContent(FeedItemCollection $items): bool;
}
