<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Exception\FeedFetchException;

interface FeedFetcherServiceInterface
{
    /**
     * Fetch raw feed content from a URL.
     *
     * @throws FeedFetchException
     */
    public function fetch(string $feedUrl): string;
}
