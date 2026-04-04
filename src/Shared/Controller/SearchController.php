<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/search', name: 'app_search')]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q', '');
        $results = [];

        if ($query !== '') {
            /** @var list<Article> $results */
            $results = $this->entityManager
                ->getRepository(Article::class)
                ->createQueryBuilder('a')
                ->where('LOWER(a.title) LIKE :q')
                ->orWhere('LOWER(a.contentText) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%')
                ->orderBy('a.score', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getResult();
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
