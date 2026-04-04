<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

enum EnrichmentMethod: string
{
    case Ai = 'ai';
    case RuleBased = 'rule_based';
}
