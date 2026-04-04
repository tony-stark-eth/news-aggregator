<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\ValueObject\EnrichmentResult;
use App\Shared\ValueObject\EnrichmentMethod;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiCategorizationService implements CategorizationServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Categorize the following news article into exactly one of these categories: politics, business, tech, science, sports.

Respond with ONLY the category slug (one word, lowercase). No explanation.

Title: %s
Content: %s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private RuleBasedCategorizationService $ruleBasedFallback,
        private AiQualityGateService $qualityGate,
        private LoggerInterface $logger,
    ) {
    }

    public function categorize(string $title, ?string $contentText): EnrichmentResult
    {
        try {
            $prompt = sprintf(
                self::PROMPT_TEMPLATE,
                $title,
                mb_substr($contentText ?? '', 0, 1000),
            );

            $input = new MessageBag(Message::ofUser($prompt));
            $categorySlug = trim(mb_strtolower($this->platform->invoke(self::MODEL, $input)->asText()));

            if ($this->qualityGate->validateCategorization($categorySlug)) {
                return new EnrichmentResult($categorySlug, EnrichmentMethod::Ai, self::MODEL);
            }

            $this->logger->info('AI categorization rejected by quality gate: {slug}', [
                'slug' => $categorySlug,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('AI categorization failed, using rule-based fallback: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->ruleBasedFallback->categorize($title, $contentText);
    }
}
