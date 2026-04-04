<?php

declare(strict_types=1);

namespace App\Source\Message;

final readonly class FetchSourceMessage
{
    public function __construct(
        public int $sourceId,
    ) {
    }
}
