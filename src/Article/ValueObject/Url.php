<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

final readonly class Url
{
    public string $value;

    public function __construct(string $url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException(sprintf('Invalid URL: "%s"', $url));
        }

        $this->value = $url;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
