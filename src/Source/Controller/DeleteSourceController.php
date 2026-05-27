<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\SourceDeletionServiceInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceDeletionRequestGuard $requestGuard,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly SourceDeletionServiceInterface $sourceDeletionService,
    ) {
    }

    #[Route('/sources/{id}/delete', name: 'app_sources_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $context = $this->requestGuard->authorize($request);
        if ($context instanceof Response) {
            return $context;
        }

        $csrfResponse = $this->requestGuard->validateCsrf(
            $request,
            'delete_source',
            $context['isHtmx'],
            $context['sourcesUrl'],
        );
        if ($csrfResponse instanceof Response) {
            return $csrfResponse;
        }

        $source = $this->sourceRepository->findById($id);
        if (! $source instanceof Source) {
            return $this->notFoundResponse($context['isHtmx'], $context['sourcesUrl']);
        }

        try {
            $this->sourceDeletionService->delete($source);
        } catch (ForeignKeyConstraintViolationException) {
            return SourceDeletionResponse::error(
                $context['isHtmx'],
                'Could not delete this source because related articles still exist. Run database migrations and try again.',
                $context['sourcesUrl'],
            );
        }

        if ($context['isHtmx']) {
            return SourceDeletionResponse::success($context['isHtmx'], $context['sourcesUrl']);
        }

        $this->controller->addFlash('success', 'Source deleted.');

        return new RedirectResponse($context['sourcesUrl']);
    }

    private function notFoundResponse(bool $isHtmx, string $sourcesUrl): Response
    {
        if ($isHtmx) {
            return SourceDeletionResponse::error($isHtmx, 'Source not found.', $sourcesUrl);
        }

        $this->controller->addFlash('error', 'Source not found.');

        return new RedirectResponse($sourcesUrl);
    }
}
