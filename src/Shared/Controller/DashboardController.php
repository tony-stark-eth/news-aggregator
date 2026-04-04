<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use App\Source\Entity\Source;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function __invoke(
        #[MapQueryParameter]
        ?string $category = null,
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter(name: '_fragment')]
        ?string $fragment = null,
    ): Response {
        $page = max(1, $page);
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

        // Build read-state set for the current user
        $readArticleIds = $this->getReadArticleIds($articles);

        // AJAX fragment for infinite scroll
        if ($fragment !== null) {
            return $this->render('dashboard/_article_list.html.twig', [
                'articles' => $articles,
                'readArticleIds' => $readArticleIds,
            ]);
        }

        return $this->render('dashboard/index.html.twig', [
            'articles' => $articles,
            'currentCategory' => $category,
            'articlesToday' => $articlesToday,
            'activeSources' => $activeSources,
            'page' => $page,
            'readArticleIds' => $readArticleIds,
        ]);
    }

    /**
     * @param list<Article> $articles
     *
     * @return array<int, true>
     */
    private function getReadArticleIds(array $articles): array
    {
        $user = $this->getUser();
        if (! $user instanceof User || $articles === []) {
            return [];
        }

        $articleIds = array_map(
            static fn (Article $a): int => (int) $a->getId(),
            $articles,
        );

        /** @var list<UserArticleRead> $readRecords */
        $readRecords = $this->entityManager
            ->getRepository(UserArticleRead::class)
            ->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.article IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $articleIds)
            ->getQuery()
            ->getResult();

        $readIds = [];
        foreach ($readRecords as $record) {
            $readIds[(int) $record->getArticle()->getId()] = true;
        }

        return $readIds;
    }
}
