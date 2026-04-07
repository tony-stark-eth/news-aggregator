<?php

declare(strict_types=1);

namespace App\Source\Service;

final readonly class FeedContentAnalyzerService implements FeedContentAnalyzerServiceInterface
{
    private const int FULL_CONTENT_WORD_THRESHOLD = 150;

    private const int MAX_SAMPLE_SIZE = 5;

    private const float FULL_CONTENT_RATIO = 0.6;

    public function hasFullContent(FeedItemCollection $items): bool
    {
        if ($items->count() === 0) {
            return false;
        }

        $sampleSize = min($items->count(), self::MAX_SAMPLE_SIZE);
        $fullContentCount = 0;

        $index = 0;
        foreach ($items as $item) {
            if ($index >= $sampleSize) {
                break;
            }

            if ($this->itemHasFullContent($item)) {
                $fullContentCount++;
            }

            $index++;
        }

        return ($fullContentCount / $sampleSize) >= self::FULL_CONTENT_RATIO;
    }

    private function itemHasFullContent(FeedItem $item): bool
    {
        $text = $item->contentText ?? $this->stripHtml($item->contentRaw);
        if ($text === null || $text === '') {
            return false;
        }

        if ($this->hasTruncationMarkers($text)) {
            return false;
        }

        return $this->countWords($text) >= self::FULL_CONTENT_WORD_THRESHOLD;
    }

    private function hasTruncationMarkers(string $text): bool
    {
        $markers = ['[...]', '...read more', '...continue reading', '(more...)', 'Read more'];
        $lowerText = mb_strtolower($text);
        return array_any($markers, fn (string $marker): bool => str_contains($lowerText, mb_strtolower($marker)));
    }

    private function countWords(string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        $words = preg_split('/\s+/', $trimmed);

        return \is_array($words) ? count($words) : 0;
    }

    private function stripHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return trim(strip_tags($html));
    }
}
