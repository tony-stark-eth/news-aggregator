<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReadStateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/articles/{id}/read', name: 'app_article_read', methods: ['POST'])]
    public function markAsRead(int $id): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            return new JsonResponse([
                'error' => 'unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $article = $this->entityManager->find(Article::class, $id);
        if ($article === null) {
            return new JsonResponse([
                'error' => 'not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if already read
        $existing = $this->entityManager->getRepository(UserArticleRead::class)->findOneBy([
            'user' => $user,
            'article' => $article,
        ]);

        if ($existing === null) {
            $read = new UserArticleRead($user, $article, $this->clock->now());
            $this->entityManager->persist($read);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}
