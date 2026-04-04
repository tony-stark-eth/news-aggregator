<?php

declare(strict_types=1);

namespace App\Source\ValueObject;

enum SourceHealth: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Failing = 'failing';
    case Disabled = 'disabled';
}
