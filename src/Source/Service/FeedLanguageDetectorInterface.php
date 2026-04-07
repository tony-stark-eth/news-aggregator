<?php

declare(strict_types=1);

namespace App\Source\Service;

interface FeedLanguageDetectorInterface
{
    /**
     * Detect the language of a feed from its raw XML content.
     *
     * Checks RSS <language>, Atom xml:lang, dc:language, and falls back
     * to character heuristic on the first item title.
     */
    public function detect(string $feedContent): ?string;
}
