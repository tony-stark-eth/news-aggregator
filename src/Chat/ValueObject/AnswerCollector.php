<?php

declare(strict_types=1);

namespace App\Chat\ValueObject;

/**
 * Mutable collector for accumulating streamed answer text.
 *
 * @internal
 */
final class AnswerCollector
{
    private string $text = '';

    public function append(string $chunk): void
    {
        $this->text .= $chunk;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
