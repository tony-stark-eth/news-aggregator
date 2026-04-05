<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/sources/{id}/delete', name: 'app_sources_delete', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $this->requestStack->getCurrentRequest()?->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_source', $token)) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources'));
        }

        $source = $this->sourceRepository->findById($id);
        if ($source === null) {
            $this->controller->addFlash('error', 'Source not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources'));
        }

        $this->sourceRepository->remove($source, flush: true);

        $this->controller->addFlash('success', 'Source deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }
}
