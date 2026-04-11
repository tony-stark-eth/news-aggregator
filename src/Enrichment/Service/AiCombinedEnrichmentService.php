<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\CombinedEnrichmentResult;
use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiCombinedEnrichmentService implements CombinedEnrichmentServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const int CONTENT_TRUNCATION = 2000;

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Analyze the following news article and return a JSON object with exactly these fields:
- "category": one of [politics, business, tech, science, sports, entertainment, health, world]
- "summary": 1-2 concise sentences summarizing the key facts
- "keywords": array of 3-5 key entities (people, organizations, places, topics)
- "sentiment_score": float from -1.0 (very negative) to +1.0 (very positive), 0.0 = neutral

Title: %s
Content: %s

Respond with ONLY the JSON object. No explanation, no markdown.
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private CategorizationServiceInterface $categorizationFallback,
        private SummarizationServiceInterface $summarizationFallback,
        private KeywordExtractionServiceInterface $keywordExtractionFallback,
        private AiQualityGateServiceInterface $qualityGate,
        private ModelQualityTrackerInterface $qualityTracker,
        private AiTextCleanupServiceInterface $textCleanup,
        private KeywordFilterService $keywordFilter,
        private LoggerInterface $logger,
    ) {
    }

    public function enrich(string $title, ?string $contentText): CombinedEnrichmentResult
    {
        try {
            $result = $this->tryAiEnrichment($title, $contentText);
            if ($result instanceof CombinedEnrichmentResult) {
                return $result;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Combined AI enrichment failed: {error}', [
                'error' => $e->getMessage(),
                'model' => self::MODEL,
            ]);
        }

        return $this->fallbackToIndividual($title, $contentText);
    }

    private function tryAiEnrichment(string $title, ?string $contentText): ?CombinedEnrichmentResult
    {
        $prompt = sprintf(
            self::PROMPT_TEMPLATE,
            $title,
            mb_substr($contentText ?? '', 0, self::CONTENT_TRUNCATION),
        );

        $response = $this->platform->invoke(self::MODEL, new MessageBag(Message::ofUser($prompt)));
        $rawText = trim($response->asText());
        /** @var string $actualModel */
        $actualModel = $response->getMetadata()->get('actual_model', self::MODEL);

        $decoded = $this->parseJson($rawText);
        if ($decoded === null) {
            $this->qualityTracker->recordRejection($actualModel);
            $this->logger->info('Combined AI enrichment JSON parse failed', [
                'response' => mb_substr($rawText, 0, 200),
                'model' => $actualModel,
            ]);

            return null;
        }

        return $this->buildResultWithPartialFallback(
            $decoded,
            $actualModel,
            $title,
            $contentText,
        );
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function buildResultWithPartialFallback(
        array $decoded,
        string $actualModel,
        string $title,
        ?string $contentText,
    ): CombinedEnrichmentResult {
        $categorySlug = $this->extractCategory($decoded);
        $summary = $this->extractSummary($decoded, $title);
        $keywords = $this->extractKeywords($decoded);
        $sentimentScore = $this->extractSentiment($decoded);

        $anyFieldFromAi = $categorySlug !== null || $summary !== null || $keywords !== [] || $sentimentScore !== null;

        if ($anyFieldFromAi) {
            $this->qualityTracker->recordAcceptance($actualModel);
        } else {
            $this->qualityTracker->recordRejection($actualModel);
        }

        // Partial fallback: fill in missing fields from individual services
        $categorySlug ??= $this->categorizationFallback->categorize($title, $contentText)->value;
        if ($summary === null && $contentText !== null) {
            $summary = $this->summarizationFallback->summarize($contentText, $title)->value;
        }
        if ($keywords === []) {
            $keywords = $this->keywordExtractionFallback->extract($title, $contentText);
        }

        $method = $anyFieldFromAi ? EnrichmentMethod::Ai : EnrichmentMethod::RuleBased;

        return new CombinedEnrichmentResult(
            $categorySlug,
            $summary,
            $keywords,
            $method,
            $anyFieldFromAi ? $actualModel : null,
            $sentimentScore,
        );
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractCategory(array $decoded): ?string
    {
        if (! isset($decoded['category']) || ! is_string($decoded['category'])) {
            return null;
        }

        $slug = trim(mb_strtolower($decoded['category']));

        return $this->qualityGate->validateCategorization($slug) ? $slug : null;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractSummary(array $decoded, string $title): ?string
    {
        if (! isset($decoded['summary']) || ! is_string($decoded['summary'])) {
            return null;
        }

        $summary = $this->textCleanup->clean(trim($decoded['summary']));

        return $this->qualityGate->validateSummary($summary, $title) ? $summary : null;
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return list<string>
     */
    private function extractKeywords(array $decoded): array
    {
        if (! isset($decoded['keywords']) || ! is_array($decoded['keywords'])) {
            return [];
        }

        $keywords = [];
        foreach ($decoded['keywords'] as $keyword) {
            if (is_string($keyword) && trim($keyword) !== '' && mb_strlen(trim($keyword)) <= 100) {
                $keywords[] = trim($keyword);
            }
        }

        return $this->keywordFilter->filter($keywords);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractSentiment(array $decoded): ?float
    {
        if (! isset($decoded['sentiment_score'])) {
            return null;
        }

        $value = $decoded['sentiment_score'];
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        $score = (float) $value;

        return $this->qualityGate->validateSentiment($score) ? $score : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $rawText): ?array
    {
        // Strip markdown-wrapped JSON (```json ... ```)
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

    private function fallbackToIndividual(string $title, ?string $contentText): CombinedEnrichmentResult
    {
        $catResult = $this->categorizationFallback->categorize($title, $contentText);
        $sumResult = $contentText !== null
            ? $this->summarizationFallback->summarize($contentText, $title)
            : null;
        $keywords = $this->keywordExtractionFallback->extract($title, $contentText);

        $method = $catResult->method === EnrichmentMethod::Ai
            || ($sumResult instanceof EnrichmentResult && $sumResult->method === EnrichmentMethod::Ai)
                ? EnrichmentMethod::Ai
                : EnrichmentMethod::RuleBased;

        $modelUsed = $catResult->modelUsed ?? $sumResult?->modelUsed;

        return new CombinedEnrichmentResult(
            $catResult->value,
            $sumResult?->value,
            $keywords,
            $method,
            $modelUsed,
        );
    }
}
