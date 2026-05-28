<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

enum AiProvider: string
{
    case OpenRouter = 'openrouter';
    case OpenAi = 'openai';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::OpenRouter;
    }
}
