<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/sources', name: 'app_sources')]
    public function __invoke(): Response
    {
        /** @var list<Source> $sources */
        $sources = $this->entityManager->getRepository(Source::class)->findAll();

        return $this->controller->render('source/index.html.twig', [
            'sources' => $sources,
        ]);
    }
}
