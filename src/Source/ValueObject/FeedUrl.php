<?php

declare(strict_types=1);

namespace App\Source\ValueObject;

use App\Source\Exception\InvalidFeedUrlException;

final readonly class FeedUrl
{
    public string $value;

    public function __construct(string $url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw InvalidFeedUrlException::fromUrl($url);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw InvalidFeedUrlException::fromUrl($url);
        }

        $this->value = $url;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
