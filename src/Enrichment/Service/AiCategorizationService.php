<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\AI\Service\ModelQualityTrackerInterface;
use App\Shared\ValueObject\EnrichmentMethod;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiCategorizationService implements CategorizationServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const int MAX_AI_ATTEMPTS = 1;

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Categorize the following news article into exactly one of these categories: politics, business, tech, science, sports.

Respond with ONLY the category slug (one word, lowercase). No explanation.

Title: %s
Content: %s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private CategorizationServiceInterface $ruleBasedFallback,
        private AiQualityGateServiceInterface $qualityGate,
        private ModelQualityTrackerInterface $qualityTracker,
        private LoggerInterface $logger,
    ) {
    }

    public function categorize(string $title, ?string $contentText): EnrichmentResult
    {
        $prompt = sprintf(
            self::PROMPT_TEMPLATE,
            $title,
            mb_substr($contentText ?? '', 0, 1000),
        );
        $input = new MessageBag(Message::ofUser($prompt));

        for ($attempt = 1; $attempt <= self::MAX_AI_ATTEMPTS; $attempt++) {
            try {
                $result = $this->platform->invoke(self::MODEL, $input);
                $categorySlug = trim(mb_strtolower($result->asText()));
                /** @var string $actualModel */
                $actualModel = $result->getMetadata()->get('actual_model', self::MODEL);

                if ($this->qualityGate->validateCategorization($categorySlug)) {
                    $this->qualityTracker->recordAcceptance($actualModel);

                    return new EnrichmentResult($categorySlug, EnrichmentMethod::Ai, $actualModel);
                }

                $this->qualityTracker->recordRejection($actualModel);
                $this->logger->info('AI categorization rejected (attempt {attempt}/{max}): {slug}', [
                    'attempt' => $attempt,
                    'max' => self::MAX_AI_ATTEMPTS,
                    'slug' => $categorySlug,
                    'model' => $actualModel,
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('AI categorization failed (attempt {attempt}/{max}): {error}', [
                    'attempt' => $attempt,
                    'max' => self::MAX_AI_ATTEMPTS,
                    'error' => $e->getMessage(),
                    'model' => self::MODEL,
                ]);
            }
        }

        return $this->ruleBasedFallback->categorize($title, $contentText);
    }
}
