<?php

declare(strict_types=1);

namespace App\Notification\Dto;

use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;

final readonly class AlertRuleFixture
{
    /**
     * @param list<string> $keywords
     * @param list<string> $categories
     */
    public function __construct(
        public string $name,
        public AlertRuleType $type,
        public array $keywords,
        public ?string $contextPrompt,
        public AlertUrgency $urgency,
        public int $severityThreshold,
        public int $cooldownMinutes,
        public array $categories,
        public bool $enabled,
    ) {
    }
}
