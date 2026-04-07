<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

final readonly class Url
{
    private const array TRACKING_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'fbclid',
        'gclid',
        'mc_cid',
        'mc_eid',
    ];

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

    /**
     * Strips tracking query parameters (UTM, fbclid, gclid, etc.) from a URL.
     * Preserves path, fragment, and all non-tracking query parameters.
     */
    public static function sanitize(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return $url;
        }

        if (! isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $params);

        $filtered = array_diff_key($params, array_flip(self::TRACKING_PARAMS));

        $result = $parsed['scheme'] . '://'
            . (isset($parsed['user']) ? $parsed['user'] . (isset($parsed['pass']) ? ':' . $parsed['pass'] : '') . '@' : '')
            . $parsed['host']
            . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
            . ($parsed['path'] ?? '/');

        if ($filtered !== []) {
            $result .= '?' . http_build_query($filtered);
        }

        if (isset($parsed['fragment'])) {
            $result .= '#' . $parsed['fragment'];
        }

        return $result;
    }
}
