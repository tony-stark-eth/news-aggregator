<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use App\User\Repository\UserArticleReadRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarkAllReadController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleReadRepositoryInterface $userArticleReadRepository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/articles/read-all', name: 'app_articles_read_all', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('mark_all_read', $token)) {
            if ($request->headers->has('HX-Request')) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        $this->markUnreadArticles($user);

        if ($request->headers->has('HX-Request')) {
            return $this->renderArticleFeed($user);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    private function markUnreadArticles(User $user): void
    {
        $now = $this->clock->now();
        $unreadArticles = $this->articleRepository->findUnreadForUser($user);

        foreach ($unreadArticles as $article) {
            $this->userArticleReadRepository->save(new UserArticleRead($user, $article, $now));
        }

        $this->userArticleReadRepository->flush();
    }

    private function renderArticleFeed(User $user): Response
    {
        $limit = 20;
        $articles = $this->articleRepository->findPaginated(null, null, 1, $limit);

        $articleIds = array_map(
            static fn (Article $a): int => (int) $a->getId(),
            $articles,
        );

        $readArticleIds = $this->userArticleReadRepository->findReadArticleIdsForUser(
            $user,
            $articleIds,
        );

        return $this->controller->render('dashboard/_article_list.html.twig', [
            'articles' => $articles,
            'readArticleIds' => $readArticleIds,
            'currentPage' => 1,
            'currentCategory' => null,
            'unreadOnly' => false,
            'hasMore' => \count($articles) >= $limit,
        ]);
    }
}
