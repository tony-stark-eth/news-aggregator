<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleSearchServiceInterface $searchService,
    ) {
    }

    #[Route('/search', name: 'app_search')]
    public function __invoke(
        #[MapQueryParameter]
        string $q = '',
        #[MapQueryParameter]
        string $category = '',
    ): Response {
        $query = $q;
        $categorySlug = $category !== '' ? $category : null;
        $results = [];

        if ($query !== '') {
            $ids = $this->searchService->search($query, $categorySlug);

            if ($ids !== []) {
                /** @var list<Article> $results */
                $results = $this->entityManager
                    ->getRepository(Article::class)
                    ->createQueryBuilder('a')
                    ->where('a.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->orderBy('a.score', 'DESC')
                    ->getQuery()
                    ->getResult();
            }
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
