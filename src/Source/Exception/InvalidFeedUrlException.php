<?php

declare(strict_types=1);

namespace App\Source\Exception;

final class InvalidFeedUrlException extends \InvalidArgumentException
{
    public static function fromUrl(string $url): self
    {
        return new self(sprintf('Invalid feed URL: "%s"', $url));
    }
}
