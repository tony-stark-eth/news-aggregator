<?php

declare(strict_types=1);

namespace App\Article\Service;

use App\Article\ValueObject\ReadabilityResult;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class ReadabilityExtractorService implements ReadabilityExtractorServiceInterface
{
    private const int MIN_WORD_COUNT = 50;

    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.fulltext_sanitizer')]
        private HtmlSanitizerInterface $htmlSanitizer,
        private LoggerInterface $logger,
    ) {
    }

    public function extract(string $html, string $url): ReadabilityResult
    {
        try {
            $configuration = new Configuration([
                'FixRelativeURLs' => true,
                'OriginalURL' => $url,
            ]);

            $readability = new Readability($configuration);
            $readability->parse($html);

            $htmlContent = $readability->getContent();
            if ($htmlContent === null) {
                return new ReadabilityResult(null, null, false);
            }

            $sanitizedHtml = $this->htmlSanitizer->sanitize($htmlContent);
            $textContent = $this->htmlToText($sanitizedHtml);

            if ($this->countWords($textContent) < self::MIN_WORD_COUNT) {
                $this->logger->debug('Readability extracted too few words for {url}', [
                    'url' => $url,
                    'wordCount' => $this->countWords($textContent),
                ]);

                return new ReadabilityResult(null, null, false);
            }

            return new ReadabilityResult($textContent, $sanitizedHtml, true);
        } catch (ParseException $e) {
            $this->logger->debug('Readability parse failed for {url}: {error}', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return new ReadabilityResult(null, null, false);
        }
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
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
}
