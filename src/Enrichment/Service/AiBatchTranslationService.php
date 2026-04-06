<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\BatchTranslationResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\LanguageName;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiBatchTranslationService implements BatchTranslationServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const float SIMILARITY_THRESHOLD = 0.9;

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Translate the following from %s to %s. Return a JSON object with these fields:
- "title": translated title
- "summary": translated summary (or null if input summary is null)
- "keywords": array of translated keywords

Input:
- title: %s
- summary: %s
- keywords: %s

Respond with ONLY the JSON object. No explanation, no markdown.
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private TranslationServiceInterface $translationFallback,
        private AiTextCleanupService $textCleanup,
        private ModelQualityTrackerInterface $qualityTracker,
        private LoggerInterface $logger,
    ) {
    }

    public function translateBatch(
        string $title,
        ?string $summary,
        array $keywords,
        string $fromLanguage,
        string $toLanguage,
    ): BatchTranslationResult {
        if ($fromLanguage === $toLanguage) {
            return new BatchTranslationResult($title, $summary, $keywords, false);
        }

        try {
            $result = $this->tryAiBatch($title, $summary, $keywords, $fromLanguage, $toLanguage);
            if ($result instanceof BatchTranslationResult) {
                return $result;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Batch translation failed: {error}', [
                'error' => $e->getMessage(),
                'from' => $fromLanguage,
                'to' => $toLanguage,
                'model' => self::MODEL,
            ]);
        }

        return $this->fallbackToIndividual($title, $summary, $keywords, $fromLanguage, $toLanguage);
    }

    /**
     * @param list<string> $keywords
     */
    private function tryAiBatch(
        string $title,
        ?string $summary,
        array $keywords,
        string $fromLanguage,
        string $toLanguage,
    ): ?BatchTranslationResult {
        $fromLabel = LanguageName::labelFor($fromLanguage);
        $toLabel = LanguageName::labelFor($toLanguage);

        $prompt = sprintf(
            self::PROMPT_TEMPLATE,
            $fromLabel,
            $toLabel,
            $title,
            $summary ?? 'null',
            implode(', ', $keywords),
        );

        $response = $this->platform->invoke(self::MODEL, new MessageBag(Message::ofUser($prompt)));
        $rawText = trim($response->asText());
        /** @var string $actualModel */
        $actualModel = $response->getMetadata()->get('actual_model', self::MODEL);

        $decoded = $this->parseJson($rawText);
        if ($decoded === null) {
            $this->qualityTracker->recordRejection($actualModel);
            $this->logger->info('Batch translation JSON parse failed', [
                'response' => mb_substr($rawText, 0, 200),
                'model' => $actualModel,
            ]);

            return null;
        }

        $result = $this->validateAndBuild($decoded, $title, $summary, $keywords);
        if (! $result instanceof BatchTranslationResult) {
            $this->qualityTracker->recordRejection($actualModel);
        } else {
            $this->qualityTracker->recordAcceptance($actualModel);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $decoded
     * @param list<string> $originalKeywords
     */
    private function validateAndBuild(
        array $decoded,
        string $originalTitle,
        ?string $originalSummary,
        array $originalKeywords,
    ): ?BatchTranslationResult {
        // Title is required and must differ from original
        if (! isset($decoded['title']) || ! is_string($decoded['title'])) {
            return null;
        }

        $translatedTitle = $this->textCleanup->clean(trim($decoded['title']));
        if ($translatedTitle === '' || $this->isTooSimilar($originalTitle, $translatedTitle)) {
            $this->logger->info('Batch translation title too similar or empty');

            return null;
        }

        // Summary: null if original was null, otherwise validate
        $translatedSummary = null;
        if ($originalSummary !== null) {
            if (! isset($decoded['summary']) || ! is_string($decoded['summary'])) {
                return null;
            }
            $translatedSummary = $this->textCleanup->clean(trim($decoded['summary']));
            if ($translatedSummary === '') {
                return null;
            }
        }

        // Keywords: must be array of strings
        $translatedKeywords = $this->extractKeywords($decoded, $originalKeywords);
        if ($translatedKeywords === null) {
            return null;
        }

        return new BatchTranslationResult(
            $translatedTitle,
            $translatedSummary,
            $translatedKeywords,
            true,
        );
    }

    /**
     * @param array<string, mixed> $decoded
     * @param list<string> $originalKeywords
     *
     * @return list<string>|null
     */
    private function extractKeywords(array $decoded, array $originalKeywords): ?array
    {
        if ($originalKeywords === []) {
            return [];
        }

        if (! isset($decoded['keywords']) || ! is_array($decoded['keywords'])) {
            return null;
        }

        $keywords = [];
        foreach ($decoded['keywords'] as $kw) {
            if (! is_string($kw) || trim($kw) === '') {
                return null;
            }
            $keywords[] = trim($kw);
        }

        return $keywords;
    }

    private function isTooSimilar(string $original, string $translated): bool
    {
        similar_text(mb_strtolower($original), mb_strtolower($translated), $percent);

        return $percent > (self::SIMILARITY_THRESHOLD * 100);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $rawText): ?array
    {
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $stripped = preg_replace('/\s*```$/i', '', $stripped ?? $rawText);
        $stripped = trim($stripped ?? $rawText);

        try {
            $decoded = json_decode($stripped, true, 16, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return null;
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param list<string> $keywords
     */
    private function fallbackToIndividual(
        string $title,
        ?string $summary,
        array $keywords,
        string $fromLanguage,
        string $toLanguage,
    ): BatchTranslationResult {
        $translatedTitle = $this->translationFallback->translate($title, $fromLanguage, $toLanguage);
        $translatedSummary = $summary !== null
            ? $this->translationFallback->translate($summary, $fromLanguage, $toLanguage)
            : null;

        $translatedKeywords = $keywords;
        if ($keywords !== []) {
            $keywordsText = implode(', ', $keywords);
            $translatedText = $this->translationFallback->translate($keywordsText, $fromLanguage, $toLanguage);
            $translatedKeywords = array_map('trim', explode(',', $translatedText));
        }

        return new BatchTranslationResult(
            $translatedTitle,
            $translatedSummary,
            $translatedKeywords,
            false,
        );
    }
}
