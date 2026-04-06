<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserArticleReadRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleReadRepositoryInterface $userArticleReadRepository,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function __invoke(
        Request $request,
        #[MapQueryParameter]
        ?string $category = null,
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        bool $unreadOnly = false,
    ): Response {
        $page = max(1, $page);
        $limit = 20;

        $user = $this->controller->getUser();

        $articles = $this->articleRepository->findPaginated(
            $category,
            $unreadOnly && $user instanceof User ? $user : null,
            $page,
            $limit,
        );

        // Build read-state set for the current user
        $readArticleIds = $this->getReadArticleIds($articles);

        $hasMore = \count($articles) >= $limit;

        // htmx infinite scroll — return article cards + next sentinel
        if ($request->headers->has('HX-Request')) {
            return $this->controller->render('dashboard/_article_list.html.twig', [
                'articles' => $articles,
                'readArticleIds' => $readArticleIds,
                'currentPage' => $page,
                'currentCategory' => $category,
                'unreadOnly' => $unreadOnly,
                'hasMore' => $hasMore,
            ]);
        }

        // Stats
        $now = $this->clock->now();
        $todayStart = $now->setTime(0, 0);
        $articlesToday = $this->articleRepository->countSince($todayStart);

        $activeSources = $this->sourceRepository->countEnabled();

        return $this->controller->render('dashboard/index.html.twig', [
            'articles' => $articles,
            'currentCategory' => $category,
            'categories' => $this->categoryRepository->findAllOrderedByWeight(),
            'articlesToday' => $articlesToday,
            'activeSources' => $activeSources,
            'page' => $page,
            'readArticleIds' => $readArticleIds,
            'unreadOnly' => $unreadOnly,
        ]);
    }

    /**
     * @param list<Article> $articles
     *
     * @return array<int, true>
     */
    private function getReadArticleIds(array $articles): array
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User || $articles === []) {
            return [];
        }

        $articleIds = array_map(
            static fn (Article $a): int => (int) $a->getId(),
            $articles,
        );

        return $this->userArticleReadRepository->findReadArticleIdsForUser($user, $articleIds);
    }
}
