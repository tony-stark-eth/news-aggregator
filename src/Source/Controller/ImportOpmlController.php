<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Service\OpmlImportServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImportOpmlController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly OpmlImportServiceInterface $opmlImportService,
    ) {
    }

    #[Route('/sources/import', name: 'app_sources_import_opml', methods: ['GET'])]
    public function showForm(): Response
    {
        return $this->controller->render('source/import.html.twig');
    }

    #[Route('/sources/import', name: 'app_sources_import_opml_post', methods: ['POST'])]
    public function handleImport(Request $request): Response
    {
        $file = $request->files->get('opml_file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return $this->controller->render('source/import.html.twig', [
                'error' => 'Please select a valid OPML file.',
            ]);
        }

        $content = file_get_contents($file->getPathname());

        if ($content === false || $content === '') {
            return $this->controller->render('source/import.html.twig', [
                'error' => 'The uploaded file is empty.',
            ]);
        }

        try {
            $result = $this->opmlImportService->import($content);
        } catch (\InvalidArgumentException $e) {
            return $this->controller->render('source/import.html.twig', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->controller->render('source/_import_result.html.twig', [
            'result' => $result,
        ]);
    }
}
