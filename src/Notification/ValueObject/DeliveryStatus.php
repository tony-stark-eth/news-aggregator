<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

enum DeliveryStatus: string
{
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
