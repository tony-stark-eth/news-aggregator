<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(Request $request): Response
    {
        $category = $request->query->get('category');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $qb = $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.source', 's')
            ->orderBy('a.score', 'DESC')
            ->addOrderBy('a.fetchedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($category !== null && $category !== '') {
            $qb->andWhere('c.slug = :cat')->setParameter('cat', $category);
        }

        /** @var list<Article> $articles */
        $articles = $qb->getQuery()->getResult();

        // Stats
        $now = $this->clock->now();
        $todayStart = $now->setTime(0, 0);
        $articlesToday = $this->entityManager->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.fetchedAt >= :today')
            ->setParameter('today', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();

        $activeSources = $this->entityManager->getRepository(Source::class)
            ->count([
                'enabled' => true,
            ]);

        // AJAX fragment for infinite scroll
        if ($request->isXmlHttpRequest()) {
            return $this->render('dashboard/_article_list.html.twig', [
                'articles' => $articles,
            ]);
        }

        return $this->render('dashboard/index.html.twig', [
            'articles' => $articles,
            'currentCategory' => $category,
            'articlesToday' => $articlesToday,
            'activeSources' => $activeSources,
            'page' => $page,
        ]);
    }
}
