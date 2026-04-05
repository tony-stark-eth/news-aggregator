<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

enum CircuitBreakerState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
