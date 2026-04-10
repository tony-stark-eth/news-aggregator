<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ColorContrastExtension extends AbstractExtension
{
    private const float LUMINANCE_THRESHOLD = 0.179;

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('contrast_text_color', $this->contrastTextColor(...)),
        ];
    }

    /**
     * Returns '#000000' or '#ffffff' for best contrast against the given hex background.
     * Uses WCAG relative luminance formula.
     */
    public function contrastTextColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');

        if (\strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(mb_substr($hex, 0, 2)) / 255;
        $g = hexdec(mb_substr($hex, 2, 2)) / 255;
        $b = hexdec(mb_substr($hex, 4, 2)) / 255;

        $luminance = 0.2126 * $this->linearize($r)
            + 0.7152 * $this->linearize($g)
            + 0.0722 * $this->linearize($b);

        return $luminance > self::LUMINANCE_THRESHOLD ? '#000000' : '#ffffff';
    }

    private function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
