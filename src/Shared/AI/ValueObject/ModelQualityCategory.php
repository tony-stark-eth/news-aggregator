<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

enum ModelQualityCategory: string
{
    case Enrichment = 'enrichment';
    case Chat = 'chat';
    case Embedding = 'embedding';
}
