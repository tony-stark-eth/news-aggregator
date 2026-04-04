<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\ValueObject\EvaluationResult;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiAlertEvaluationService
{
    private const string MODEL = 'openrouter/auto';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Given this context: %s

Evaluate this news article:
Title: %s
Summary: %s

Rate the severity on a scale of 1-10 (where 10 is most critical).
Respond in this exact format:
SEVERITY: <number>
EXPLANATION: <one sentence explanation>
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private LoggerInterface $logger,
    ) {
    }

    public function evaluate(Article $article, AlertRule $rule): ?EvaluationResult
    {
        $contextPrompt = $rule->getContextPrompt();
        if ($contextPrompt === null || $contextPrompt === '') {
            return $this->ruleBasedFallback($article, $rule);
        }

        try {
            $prompt = sprintf(
                self::PROMPT_TEMPLATE,
                $contextPrompt,
                $article->getTitle(),
                $article->getSummary() ?? $article->getTitle(),
            );

            $input = new MessageBag(Message::ofUser($prompt));
            $result = $this->platform->invoke(self::MODEL, $input);
            $content = trim($result->asText());

            return $this->parseResponse($content);
        } catch (\Throwable $e) {
            $this->logger->warning('AI alert evaluation failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return $this->ruleBasedFallback($article, $rule);
        }
    }

    private function parseResponse(string $content): ?EvaluationResult
    {
        $hasSeverity = preg_match('/SEVERITY:\s*(\d+)/i', $content, $severityMatch) === 1;
        $hasExplanation = preg_match('/EXPLANATION:\s*(.+)/i', $content, $explanationMatch) === 1;
        if ($hasSeverity && $hasExplanation) {
            $severity = (int) $severityMatch[1];
            $explanation = trim($explanationMatch[1]);

            if ($severity >= 1 && $severity <= 10 && $explanation !== '') {
                return new EvaluationResult($severity, $explanation, self::MODEL);
            }
        }

        return null;
    }

    private function ruleBasedFallback(Article $article, AlertRule $rule): EvaluationResult
    {
        // Count keyword overlap between context_prompt and article text
        $contextWords = array_filter(
            explode(' ', mb_strtolower($rule->getContextPrompt() ?? '')),
            static fn (string $word): bool => $word !== '',
        );
        $articleText = mb_strtolower($article->getTitle() . ' ' . ($article->getSummary() ?? ''));

        $overlap = 0;
        foreach ($contextWords as $word) {
            if (mb_strlen($word) > 3 && str_contains($articleText, $word)) {
                $overlap++;
            }
        }

        $severity = min(10, max(1, (int) round($overlap * 2)));

        return new EvaluationResult($severity, 'Rule-based severity estimate based on keyword overlap');
    }
}
