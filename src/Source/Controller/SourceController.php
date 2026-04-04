<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/sources', name: 'app_sources')]
    public function __invoke(): Response
    {
        /** @var list<Source> $sources */
        $sources = $this->entityManager->getRepository(Source::class)->findAll();

        return $this->render('source/index.html.twig', [
            'sources' => $sources,
        ]);
    }
}
