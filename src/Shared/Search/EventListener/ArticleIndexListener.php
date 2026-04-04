<?php

declare(strict_types=1);

namespace App\Shared\Search\EventListener;

use App\Article\Entity\Article;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Article::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Article::class)]
final readonly class ArticleIndexListener
{
    public function __construct(
        private ArticleSearchServiceInterface $searchService,
    ) {
    }

    public function postPersist(Article $article): void
    {
        $this->searchService->index($article);
    }

    public function postUpdate(Article $article): void
    {
        $this->searchService->index($article);
    }
}
