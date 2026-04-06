<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteDigestConfigController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/digests/{id}/delete', name: 'app_digests_delete', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $this->requestStack->getCurrentRequest()?->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_digest_config', $token)) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $config = $this->digestConfigRepository->findById($id);
        if (! $config instanceof DigestConfig) {
            $this->controller->addFlash('error', 'Digest configuration not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $this->digestConfigRepository->remove($config, flush: true);

        $this->controller->addFlash('success', 'Digest configuration deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_digests'));
    }
}
