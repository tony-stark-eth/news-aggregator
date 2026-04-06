<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Shared\ValueObject\LanguageName;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class AiTranslationService implements TranslationServiceInterface
{
    private const string MODEL = 'openrouter/free';

    private const int MAX_AI_ATTEMPTS = 2;

    private const float SIMILARITY_THRESHOLD = 0.9;

    private const string PROMPT_TEMPLATE = <<<'PROMPT'
Translate the following %s text to %s. Return ONLY the translation, no explanation.

%s
PROMPT;

    public function __construct(
        private PlatformInterface $platform,
        private TranslationServiceInterface $ruleBasedFallback,
        private LoggerInterface $logger,
    ) {
    }

    public function translate(string $text, string $fromLanguage, string $toLanguage): string
    {
        if ($fromLanguage === $toLanguage) {
            return $text;
        }

        $fromLabel = LanguageName::labelFor($fromLanguage);
        $toLabel = LanguageName::labelFor($toLanguage);

        for ($attempt = 1; $attempt <= self::MAX_AI_ATTEMPTS; $attempt++) {
            try {
                $prompt = sprintf(self::PROMPT_TEMPLATE, $fromLabel, $toLabel, $text);
                $input = new MessageBag(Message::ofUser($prompt));
                $translated = trim($this->platform->invoke(self::MODEL, $input)->asText());

                if ($translated !== '' && ! $this->isTooSimilar($text, $translated)) {
                    return $translated;
                }

                $this->logRejection($text, $translated, $attempt);
            } catch (\Throwable $e) {
                $this->logger->warning('AI translation failed (attempt {attempt}/{max}): {error}', [
                    'attempt' => $attempt,
                    'max' => self::MAX_AI_ATTEMPTS,
                    'error' => $e->getMessage(),
                    'model' => self::MODEL,
                ]);
            }
        }

        return $this->ruleBasedFallback->translate($text, $fromLanguage, $toLanguage);
    }

    private function isTooSimilar(string $original, string $translated): bool
    {
        similar_text(mb_strtolower($original), mb_strtolower($translated), $percent);

        return $percent > (self::SIMILARITY_THRESHOLD * 100);
    }

    private function logRejection(string $original, string $translated, int $attempt): void
    {
        $this->logger->info('AI translation rejected (attempt {attempt}/{max}): too similar to original or empty', [
            'attempt' => $attempt,
            'max' => self::MAX_AI_ATTEMPTS,
            'original_length' => mb_strlen($original),
            'translated_length' => mb_strlen($translated),
            'model' => self::MODEL,
        ]);
    }
}
