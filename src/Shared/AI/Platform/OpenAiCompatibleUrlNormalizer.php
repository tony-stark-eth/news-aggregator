<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

final class OpenAiCompatibleUrlNormalizer
{
    public static function normalize(string $url): string
    {
        $normalized = rtrim(trim($url), '/');

        if (str_ends_with($normalized, '/v1')) {
            $normalized = substr($normalized, 0, -3);
        }

        return rtrim($normalized, '/');
    }
}
