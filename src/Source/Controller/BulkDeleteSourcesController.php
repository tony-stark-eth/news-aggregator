<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Service\SourceDeletionServiceInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BulkDeleteSourcesController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceDeletionRequestGuard $requestGuard,
        private readonly SourceDeletionServiceInterface $sourceDeletionService,
    ) {
    }

    #[Route('/sources/delete-bulk', name: 'app_sources_delete_bulk', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $context = $this->requestGuard->authorize($request);
        if ($context instanceof Response) {
            return $context;
        }

        $csrfResponse = $this->requestGuard->validateCsrf(
            $request,
            'delete_sources_bulk',
            $context['isHtmx'],
            $context['sourcesUrl'],
        );
        if ($csrfResponse instanceof Response) {
            return $csrfResponse;
        }

        $ids = $this->parseIds($request);
        if ($ids === []) {
            return $this->selectionError($context['isHtmx'], $context['sourcesUrl']);
        }

        try {
            $deletedCount = $this->sourceDeletionService->deleteMany($ids);
        } catch (ForeignKeyConstraintViolationException) {
            return SourceDeletionResponse::error(
                $context['isHtmx'],
                'Could not delete the selected sources because related articles still exist. Run database migrations and try again.',
                $context['sourcesUrl'],
            );
        }

        if ($deletedCount === 0) {
            return $this->notFoundResponse($context['isHtmx'], $context['sourcesUrl']);
        }

        $message = $deletedCount === 1
            ? '1 source deleted.'
            : sprintf('%d sources deleted.', $deletedCount);

        if ($context['isHtmx']) {
            $this->controller->addFlash('success', $message);

            return SourceDeletionResponse::refresh($context['isHtmx'], $context['sourcesUrl']);
        }

        $this->controller->addFlash('success', $message);

        return new RedirectResponse($context['sourcesUrl']);
    }

    /**
     * @return list<int>
     */
    private function parseIds(Request $request): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $request->request->all('ids')),
            static fn (int $id): bool => $id > 0,
        ));
    }

    private function selectionError(bool $isHtmx, string $sourcesUrl): Response
    {
        if ($isHtmx) {
            return SourceDeletionResponse::error($isHtmx, 'Select at least one source to delete.', $sourcesUrl);
        }

        $this->controller->addFlash('error', 'Select at least one source to delete.');

        return new RedirectResponse($sourcesUrl);
    }

    private function notFoundResponse(bool $isHtmx, string $sourcesUrl): Response
    {
        if ($isHtmx) {
            return SourceDeletionResponse::error($isHtmx, 'No matching sources found.', $sourcesUrl);
        }

        $this->controller->addFlash('error', 'No matching sources found.');

        return new RedirectResponse($sourcesUrl);
    }
}
