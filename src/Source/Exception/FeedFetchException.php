<?php

declare(strict_types=1);

namespace App\Source\Exception;

final class FeedFetchException extends \RuntimeException
{
    public static function fromUrl(string $url, string $reason, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Failed to fetch feed "%s": %s', $url, $reason), 0, $previous);
    }
}
