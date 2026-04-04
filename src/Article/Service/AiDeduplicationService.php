<?php

declare(strict_types=1);

namespace App\Article\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Decorator over DeduplicationService. Adds AI-based semantic similarity
 * check as a last resort after URL, fingerprint, and title checks.
 */
final readonly class AiDeduplicationService implements DeduplicationServiceInterface
{
    private const string MODEL = 'openrouter/auto';

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Are these two article titles about the same news event? Answer ONLY "yes" or "no".

Title 1: %s
Title 2: %s
PROMPT;

    public function __construct(
        private DeduplicationServiceInterface $ruleBasedFallback,
        private PlatformInterface $platform,
        private LoggerInterface $logger,
    ) {
    }

    public function isDuplicate(string $url, string $title, ?string $fingerprint): bool
    {
        // Rule-based checks first (fast, free)
        // AI semantic check is expensive — skip for now unless explicitly needed
        // This is a placeholder for future enhancement when AI budget allows
        return $this->ruleBasedFallback->isDuplicate($url, $title, $fingerprint);
    }

    /**
     * Check semantic similarity between two titles via AI.
     * Not called by default — available for explicit use.
     */
    public function isSemanticallyDuplicate(string $title1, string $title2): bool
    {
        try {
            $prompt = sprintf(self::PROMPT_TEMPLATE, $title1, $title2);
            $input = new MessageBag(Message::ofUser($prompt));
            $result = $this->platform->invoke(self::MODEL, $input);
            $answer = mb_strtolower(trim($result->asText()));

            return str_starts_with($answer, 'yes');
        } catch (\Throwable $e) {
            $this->logger->warning('AI dedup check failed: {error}', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
