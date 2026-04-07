<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Exception\FeedFetchException;
use App\Source\Exception\InvalidFeedUrlException;
use App\Source\ValueObject\FeedPreview;

interface FeedValidationServiceInterface
{
    /**
     * Validate a feed URL by fetching, parsing, and extracting preview info.
     *
     * @throws FeedFetchException
     * @throws InvalidFeedUrlException
     */
    public function validate(string $url): FeedPreview;
}
