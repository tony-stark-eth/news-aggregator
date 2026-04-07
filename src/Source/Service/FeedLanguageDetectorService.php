<?php

declare(strict_types=1);

namespace App\Source\Service;

use Laminas\Feed\Reader\Reader;

final readonly class FeedLanguageDetectorService implements FeedLanguageDetectorInterface
{
    public function detect(string $feedContent): ?string
    {
        $language = $this->detectFromXml($feedContent);
        if ($language !== null) {
            return $language;
        }

        return $this->detectFromItemTitle($feedContent);
    }

    private function detectFromXml(string $feedContent): ?string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = new \SimpleXMLElement($feedContent);
        } catch (\Exception) {
            libxml_use_internal_errors($previous);

            return null;
        }

        $language = $this->extractRssLanguage($xml)
            ?? $this->extractAtomLanguage($xml)
            ?? $this->extractDcLanguageFromRssItems($xml)
            ?? $this->extractDcLanguageFromAtomEntries($xml);

        libxml_use_internal_errors($previous);

        return $language;
    }

    private function extractRssLanguage(\SimpleXMLElement $xml): ?string
    {
        $channel = $xml->channel;
        if (! $channel instanceof \SimpleXMLElement) {
            return null;
        }

        $language = (string) $channel->language;
        if ($language !== '') {
            return $this->normalizeLanguageCode($language);
        }

        return null;
    }

    private function extractAtomLanguage(\SimpleXMLElement $xml): ?string
    {
        $lang = $xml->attributes('xml', true)?->lang;
        if ($lang !== null && (string) $lang !== '') {
            return $this->normalizeLanguageCode((string) $lang);
        }

        return null;
    }

    private function extractDcLanguageFromRssItems(\SimpleXMLElement $xml): ?string
    {
        $dc = 'http://purl.org/dc/elements/1.1/';
        $xpathResult = $xml->xpath('//item');
        $items = \is_array($xpathResult) ? $xpathResult : [];

        return $this->findDcLanguage($items, $dc);
    }

    private function extractDcLanguageFromAtomEntries(\SimpleXMLElement $xml): ?string
    {
        $dc = 'http://purl.org/dc/elements/1.1/';
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $xpathResult = $xml->xpath('//atom:entry');
        $entries = \is_array($xpathResult) ? $xpathResult : [];

        return $this->findDcLanguage($entries, $dc);
    }

    /**
     * @param array<\SimpleXMLElement> $elements
     */
    private function findDcLanguage(array $elements, string $dcNamespace): ?string
    {
        foreach ($elements as $element) {
            $dcChildren = $element->children($dcNamespace);
            if (! $dcChildren instanceof \SimpleXMLElement) {
                continue;
            }

            $language = (string) $dcChildren->language;
            if ($language !== '') {
                return $this->normalizeLanguageCode($language);
            }
        }

        return null;
    }

    private function detectFromItemTitle(string $feedContent): ?string
    {
        try {
            $feed = Reader::importString($feedContent);
        } catch (\Exception) {
            return null;
        }

        foreach ($feed as $entry) {
            $title = $entry->getTitle();
            /** @phpstan-ignore notIdentical.alwaysTrue, voku.NotIdentical */
            if ($title !== null && $title !== '') {
                return $this->detectLanguageByCharacters($title);
            }
        }

        return null;
    }

    private function detectLanguageByCharacters(string $text): ?string
    {
        if (preg_match('/[äöüÄÖÜß]/u', $text) === 1) {
            return 'de';
        }

        if (preg_match('/[éèêëàâçîïôùûœæ]/ui', $text) === 1) {
            return 'fr';
        }

        if (preg_match('/[ñ¿¡]/u', $text) === 1) {
            return 'es';
        }

        return null;
    }

    private function normalizeLanguageCode(string $code): string
    {
        $code = trim($code);
        $parts = explode('-', $code);

        return mb_strtolower($parts[0]);
    }
}
