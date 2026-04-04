<?php

declare(strict_types=1);

namespace App\Source\Service;

interface FeedParserServiceInterface
{
    /**
     * Parse raw feed XML content into a typed collection of parsed items.
     */
    public function parse(string $feedContent): FeedItemCollection;
}
