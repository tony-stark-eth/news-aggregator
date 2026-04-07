<?php

declare(strict_types=1);

namespace App\Article\Service;

interface ArticleContentFetcherServiceInterface
{
    /**
     * Fetches the raw HTML content of a URL.
     *
     * @throws \RuntimeException when the fetch fails
     */
    public function fetch(string $url): string;
}
