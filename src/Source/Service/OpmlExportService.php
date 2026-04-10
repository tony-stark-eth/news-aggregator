<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;

final readonly class OpmlExportService implements OpmlExportServiceInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {
    }

    public function export(): string
    {
        $sources = $this->sourceRepository->findAll();
        $grouped = $this->groupByCategory($sources);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $opml = $dom->createElement('opml');
        $opml->setAttribute('version', '2.0');
        $dom->appendChild($opml);

        $head = $dom->createElement('head');
        $opml->appendChild($head);
        $title = $dom->createElement('title', 'News Aggregator Feeds');
        $head->appendChild($title);

        $body = $dom->createElement('body');
        $opml->appendChild($body);

        foreach ($grouped as $categoryName => $categorySources) {
            $categoryOutline = $dom->createElement('outline');
            $categoryOutline->setAttribute('text', $categoryName);
            $categoryOutline->setAttribute('title', $categoryName);
            $body->appendChild($categoryOutline);

            foreach ($categorySources as $source) {
                $this->appendSourceOutline($dom, $categoryOutline, $source);
            }
        }

        return (string) $dom->saveXML();
    }

    /**
     * @param list<Source> $sources
     *
     * @return array<string, list<Source>>
     */
    private function groupByCategory(array $sources): array
    {
        $grouped = [];
        foreach ($sources as $source) {
            $categoryName = $source->getCategory()->getName();
            $grouped[$categoryName][] = $source;
        }

        ksort($grouped);

        return $grouped;
    }

    private function appendSourceOutline(
        \DOMDocument $dom,
        \DOMElement $parent,
        Source $source,
    ): void {
        $outline = $dom->createElement('outline');
        $outline->setAttribute('type', 'rss');
        $outline->setAttribute('text', $source->getName());
        $outline->setAttribute('title', $source->getName());
        $outline->setAttribute('xmlUrl', $source->getFeedUrl());

        if ($source->getSiteUrl() !== null) {
            $outline->setAttribute('htmlUrl', $source->getSiteUrl());
        }

        $parent->appendChild($outline);
    }
}
