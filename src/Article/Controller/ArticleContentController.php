<?php

declare(strict_types=1);

namespace App\Article\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleContentController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
    ) {
    }

    #[Route('/articles/{id}/content', name: 'app_article_content', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new Response('Unauthorized', 401);
        }

        $article = $this->articleRepository->findById($id);
        if (! $article instanceof Article) {
            return new Response('Not found', 404);
        }

        return $this->controller->render('components/_article_content.html.twig', [
            'article' => $article,
        ]);
    }
}
