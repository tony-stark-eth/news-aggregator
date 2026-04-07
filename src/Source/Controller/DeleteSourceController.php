<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sources/{id}/delete', name: 'app_sources_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_source', $token)) {
            if ($isHtmx) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources'));
        }

        $source = $this->sourceRepository->findById($id);
        if (! $source instanceof Source) {
            if ($isHtmx) {
                return new Response('Source not found.', Response::HTTP_NOT_FOUND);
            }

            $this->controller->addFlash('error', 'Source not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources'));
        }

        $this->sourceRepository->remove($source, flush: true);

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash('success', 'Source deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }
}
