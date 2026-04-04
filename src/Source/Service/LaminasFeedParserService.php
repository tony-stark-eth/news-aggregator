<?php

declare(strict_types=1);

namespace App\Source\Service;

use Laminas\Feed\Reader\Reader;

final readonly class LaminasFeedParserService implements FeedParserServiceInterface
{
    public function parse(string $feedContent): FeedItemCollection
    {
        $feed = Reader::importString($feedContent);
        $items = [];

        foreach ($feed as $entry) {
            $title = $entry->getTitle();
            $url = $entry->getLink();

            /** @phpstan-ignore identical.alwaysFalse, voku.Identical */
            $titleEmpty = $title === null || $title === '';
            /** @phpstan-ignore identical.alwaysFalse, voku.Identical */
            $urlEmpty = $url === null || $url === '';
            if ($titleEmpty) {
                continue;
            }
            if ($urlEmpty) {
                continue;
            }

            $contentRaw = $entry->getContent();
            if ($contentRaw === '') {
                $contentRaw = $entry->getDescription();
            }

            $contentText = $contentRaw !== '' ? $this->stripHtml($contentRaw) : null;
            $contentRawOrNull = $contentRaw !== '' ? $contentRaw : null;

            $publishedAt = null;
            $dateModified = $entry->getDateModified();
            if ($dateModified instanceof \DateTimeInterface) {
                $publishedAt = \DateTimeImmutable::createFromInterface($dateModified);
            }

            $items[] = new FeedItem(
                title: $title,
                url: $url,
                contentRaw: $contentRawOrNull,
                contentText: $contentText,
                publishedAt: $publishedAt,
            );
        }

        return new FeedItemCollection($items);
    }

    private function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
