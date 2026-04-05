<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Article\Entity\Article;
use App\User\Entity\User;
use App\User\Entity\UserArticleRead;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarkAllReadController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/articles/read-all', name: 'app_articles_read_all', methods: ['POST'])]
    public function __invoke(): RedirectResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $this->requestStack->getCurrentRequest()?->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('mark_all_read', $token)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        $this->markUnreadArticles($user);

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    private function markUnreadArticles(User $user): void
    {
        $now = $this->clock->now();

        $subDql = $this->entityManager
            ->getRepository(UserArticleRead::class)
            ->createQueryBuilder('r')
            ->select('IDENTITY(r.article)')
            ->where('r.user = :user')
            ->getDQL();

        /** @var list<Article> $unreadArticles */
        $unreadArticles = $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->where("a.id NOT IN ({$subDql})")
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        foreach ($unreadArticles as $article) {
            $read = new UserArticleRead($user, $article, $now);
            $this->entityManager->persist($read);
        }

        $this->entityManager->flush();
    }
}
