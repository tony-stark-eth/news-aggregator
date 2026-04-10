<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Service\OpmlExportServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExportOpmlController
{
    public function __construct(
        private readonly OpmlExportServiceInterface $opmlExportService,
    ) {
    }

    #[Route('/sources/export.opml', name: 'app_sources_export_opml', methods: ['GET'])]
    public function __invoke(): Response
    {
        $xml = $this->opmlExportService->export();

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="feeds.opml"',
        ]);
    }
}
