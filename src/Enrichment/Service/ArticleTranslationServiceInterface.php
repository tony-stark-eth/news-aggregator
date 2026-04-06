<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Article\Entity\Article;
use App\Source\Entity\Source;

interface ArticleTranslationServiceInterface
{
    public function applyTranslations(Article $article, Source $source): void;
}
