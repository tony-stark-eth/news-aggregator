<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestLog;
use App\Digest\Repository\DigestLogRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ViewDigestLogController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestLogRepositoryInterface $digestLogRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/digests/log/{id}', name: 'app_digests_log_view', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $log = $this->digestLogRepository->findById($id);
        if (! $log instanceof DigestLog) {
            $this->controller->addFlash('error', 'Digest log not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        return $this->controller->render('digest/view_log.html.twig', [
            'log' => $log,
        ]);
    }
}
