<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Repository\SourceRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
    ) {
    }

    #[Route('/sources', name: 'app_sources')]
    public function __invoke(): Response
    {
        return $this->controller->render('source/index.html.twig', [
            'sources' => $this->sourceRepository->findAll(),
        ]);
    }
}
