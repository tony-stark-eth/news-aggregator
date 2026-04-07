<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\ValueObject\FeedPreview;
use App\Source\ValueObject\FeedUrl;
use Laminas\Feed\Reader\Reader;

final readonly class FeedValidationService implements FeedValidationServiceInterface
{
    public function __construct(
        private FeedFetcherServiceInterface $feedFetcher,
        private FeedParserServiceInterface $feedParser,
        private FeedLanguageDetectorInterface $languageDetector,
    ) {
    }

    public function validate(string $url): FeedPreview
    {
        $feedUrl = new FeedUrl($url);

        $rawContent = $this->feedFetcher->fetch($feedUrl->value);
        $items = $this->feedParser->parse($rawContent);

        $title = $this->extractTitle($rawContent);
        $detectedLanguage = $this->languageDetector->detect($rawContent);

        return new FeedPreview(
            title: $title,
            itemCount: $items->count(),
            detectedLanguage: $detectedLanguage,
            feedUrl: $feedUrl,
        );
    }

    private function extractTitle(string $rawContent): string
    {
        try {
            $feed = Reader::importString($rawContent);

            return $feed->getTitle() ?? 'Unknown Feed';
        } catch (\Exception) {
            return 'Unknown Feed';
        }
    }
}
