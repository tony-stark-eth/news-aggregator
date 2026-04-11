<?php

declare(strict_types=1);

namespace App\Chat\ValueObject;

enum SearchSource: string
{
    case Keyword = 'keyword';
    case Semantic = 'semantic';
    case Hybrid = 'hybrid';
}
