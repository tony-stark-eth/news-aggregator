<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteDigestConfigController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/digests/{id}/delete', name: 'app_digests_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_digest_config', $token)) {
            if ($isHtmx) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $config = $this->digestConfigRepository->findById($id);
        if (! $config instanceof DigestConfig) {
            if ($isHtmx) {
                return new Response('Digest configuration not found.', Response::HTTP_NOT_FOUND);
            }

            $this->controller->addFlash('error', 'Digest configuration not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $this->digestConfigRepository->remove($config, flush: true);

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash('success', 'Digest configuration deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_digests'));
    }
}
