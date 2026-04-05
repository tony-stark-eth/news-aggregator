<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use App\User\Repository\UserArticleReadRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReadStateController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleReadRepositoryInterface $userArticleReadRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/articles/{id}/read', name: 'app_article_read', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new JsonResponse([
                'error' => 'unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $article = $this->articleRepository->findById($id);
        if ($article === null) {
            return new JsonResponse([
                'error' => 'not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if already read
        $existing = $this->userArticleReadRepository->findByUserAndArticle($user, $article);

        if ($existing === null) {
            $read = new UserArticleRead($user, $article, $this->clock->now());
            $this->userArticleReadRepository->save($read, flush: true);
        }

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
