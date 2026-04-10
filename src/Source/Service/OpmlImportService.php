<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Dto\OpmlImportResultDto;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use Psr\Clock\ClockInterface;

final readonly class OpmlImportService implements OpmlImportServiceInterface
{
    private const string DEFAULT_CATEGORY_COLOR = '#6B7280';

    private const int DEFAULT_CATEGORY_WEIGHT = 5;

    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClockInterface $clock,
    ) {
    }

    public function import(string $opmlContent): OpmlImportResultDto
    {
        $xml = $this->parseXml($opmlContent);
        $outlines = $this->extractOutlines($xml);

        $importedNames = [];
        $skippedNames = [];

        /** @var array<string, Category> $categoryCache */
        $categoryCache = [];

        foreach ($outlines as $outline) {
            $feedUrl = $outline['xmlUrl'];
            $name = $outline['name'];

            $existing = $this->sourceRepository->findByFeedUrl($feedUrl);
            if ($existing instanceof Source) {
                $skippedNames[] = $name;
                continue;
            }

            $category = $this->resolveCategory($outline['category'], $categoryCache);
            $source = new Source($name, $feedUrl, $category, $this->clock->now());

            if ($outline['htmlUrl'] !== null) {
                $source->setSiteUrl($outline['htmlUrl']);
            }

            $this->sourceRepository->save($source);
            $importedNames[] = $name;
        }

        $this->sourceRepository->flush();

        return new OpmlImportResultDto(
            importedCount: \count($importedNames),
            skippedCount: \count($skippedNames),
            importedNames: $importedNames,
            skippedNames: $skippedNames,
        );
    }

    private function parseXml(string $content): \SimpleXMLElement
    {
        $previousValue = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($content);

            if ($xml === false) {
                throw new \InvalidArgumentException('Invalid OPML: could not parse XML.');
            }

            return $xml;
        } finally {
            libxml_use_internal_errors($previousValue);
        }
    }

    /**
     * @return list<array{name: string, xmlUrl: string, htmlUrl: ?string, category: ?string}>
     */
    private function extractOutlines(\SimpleXMLElement $xml): array
    {
        $outlines = [];

        foreach ($xml->body->outline ?? [] as $topLevel) {
            $type = (string) ($topLevel['type'] ?? '');

            if (mb_strtolower($type) === 'rss' || isset($topLevel['xmlUrl'])) {
                $outlines[] = $this->buildOutlineData($topLevel, null);
                continue;
            }

            $categoryName = (string) ($topLevel['text'] ?? $topLevel['title'] ?? '');
            foreach ($topLevel->outline ?? [] as $child) {
                if (! isset($child['xmlUrl'])) {
                    continue;
                }

                $outlines[] = $this->buildOutlineData($child, $categoryName !== '' ? $categoryName : null);
            }
        }

        return $outlines;
    }

    /**
     * @return array{name: string, xmlUrl: string, htmlUrl: ?string, category: ?string}
     */
    private function buildOutlineData(\SimpleXMLElement $outline, ?string $category): array
    {
        $name = (string) ($outline['text'] ?? $outline['title'] ?? '');
        $xmlUrl = (string) $outline['xmlUrl'];

        if ($name === '') {
            $name = $xmlUrl;
        }

        $htmlUrl = isset($outline['htmlUrl']) ? (string) $outline['htmlUrl'] : null;

        if ($htmlUrl === '') {
            $htmlUrl = null;
        }

        return [
            'name' => $name,
            'xmlUrl' => $xmlUrl,
            'htmlUrl' => $htmlUrl,
            'category' => $category,
        ];
    }

    /**
     * @param array<string, Category> $cache
     */
    private function resolveCategory(?string $categoryName, array &$cache): Category
    {
        $name = $categoryName ?? 'Uncategorized';

        return $this->getOrCreateCategory($name, $cache);
    }

    /**
     * @param array<string, Category> $cache
     */
    private function getOrCreateCategory(string $name, array &$cache): Category
    {
        $slug = $this->generateSlug($name);

        if (isset($cache[$slug])) {
            return $cache[$slug];
        }

        $existing = $this->categoryRepository->findBySlug($slug);

        if ($existing instanceof Category) {
            $cache[$slug] = $existing;

            return $existing;
        }

        $category = new Category($name, $slug, self::DEFAULT_CATEGORY_WEIGHT, self::DEFAULT_CATEGORY_COLOR);
        $this->categoryRepository->save($category, flush: true);
        $cache[$slug] = $category;

        return $category;
    }

    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}
