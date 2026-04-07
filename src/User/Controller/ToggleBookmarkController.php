<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ToggleBookmarkController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleBookmarkRepositoryInterface $bookmarkRepository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/articles/{id}/bookmark', name: 'app_article_bookmark', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('bookmark_article', $token)) {
            return $this->errorResponse($isHtmx, 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $article = $this->articleRepository->findById($id);
        if (! $article instanceof Article) {
            return $this->errorResponse($isHtmx, 'Article not found.', Response::HTTP_NOT_FOUND);
        }

        $isBookmarked = $this->toggleBookmark($user, $article);

        if ($isHtmx) {
            return $this->controller->render('components/_bookmark_button.html.twig', [
                'article' => $article,
                'isBookmarked' => $isBookmarked,
            ]);
        }

        $this->controller->addFlash('success', $isBookmarked ? 'Article bookmarked.' : 'Bookmark removed.');

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    private function toggleBookmark(User $user, Article $article): bool
    {
        $existing = $this->bookmarkRepository->findByUserAndArticle($user, $article);

        if ($existing instanceof UserArticleBookmark) {
            $this->bookmarkRepository->remove($existing, true);

            return false;
        }

        $this->bookmarkRepository->save(new UserArticleBookmark($user, $article, $this->clock->now()), true);

        return true;
    }

    private function errorResponse(bool $isHtmx, string $message, int $statusCode): Response
    {
        if ($isHtmx) {
            return new Response($message, $statusCode);
        }

        $this->controller->addFlash('error', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }
}
