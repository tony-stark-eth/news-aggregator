<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

enum FullTextStatus: string
{
    case Pending = 'pending';
    case Fetched = 'fetched';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
