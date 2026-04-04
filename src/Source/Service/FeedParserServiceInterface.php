<?php

declare(strict_types=1);

namespace App\Source\Service;

interface FeedParserServiceInterface
{
    /**
     * Parse raw feed XML content into an array of parsed items.
     *
     * @return list<FeedItem>
     */
    public function parse(string $feedContent): array;
}
