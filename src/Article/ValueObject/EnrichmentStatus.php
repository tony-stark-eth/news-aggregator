<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

enum EnrichmentStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
}
