<?php

declare(strict_types=1);

namespace App\Digest\Message;

final readonly class GenerateDigestMessage
{
    public function __construct(
        public int $digestConfigId,
    ) {
    }
}
