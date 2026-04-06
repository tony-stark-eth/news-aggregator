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

final readonly class AiSummarizationService implements SummarizationServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Summarize the following news article in 1-2 concise sentences. Focus on the key facts.

Content: %s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private SummarizationServiceInterface $ruleBasedFallback,
        private AiQualityGateServiceInterface $qualityGate,
        private ModelQualityTrackerInterface $qualityTracker,
        private LoggerInterface $logger,
    ) {
    }

    public function summarize(string $contentText, string $title = ''): EnrichmentResult
    {
        try {
            $prompt = sprintf(self::PROMPT_TEMPLATE, mb_substr($contentText, 0, 2000));

            $input = new MessageBag(Message::ofUser($prompt));
            $result = $this->platform->invoke(self::MODEL, $input);
            $summary = trim($result->asText());
            /** @var string $actualModel */
            $actualModel = $result->getMetadata()->get('actual_model', self::MODEL);

            if ($this->qualityGate->validateSummary($summary, $title)) {
                $this->qualityTracker->recordAcceptance($actualModel);

                return new EnrichmentResult($summary, EnrichmentMethod::Ai, $actualModel);
            }

            $this->qualityTracker->recordRejection($actualModel);
            $this->logger->info('AI summary rejected by quality gate', [
                'length' => mb_strlen($summary),
                'model' => $actualModel,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('AI summarization failed, using rule-based fallback: {error}', [
                'error' => $e->getMessage(),
                'model' => self::MODEL,
            ]);
        }

        return $this->ruleBasedFallback->summarize($contentText, $title);
    }
}
