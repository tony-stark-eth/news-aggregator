<?php

declare(strict_types=1);

namespace App\Source\Service;

interface FeedFetcherServiceInterface
{
    /**
     * Fetch raw feed content from a URL.
     *
     * @throws \App\Source\Exception\FeedFetchException
     */
    public function fetch(string $feedUrl): string;
}
