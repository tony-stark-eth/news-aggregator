<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DigestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/digests', name: 'app_digests')]
    public function index(): Response
    {
        /** @var list<DigestConfig> $configs */
        $configs = $this->entityManager->getRepository(DigestConfig::class)->findAll();

        /** @var list<DigestLog> $recentLogs */
        $recentLogs = $this->entityManager
            ->getRepository(DigestLog::class)
            ->createQueryBuilder('l')
            ->orderBy('l.generatedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('digest/index.html.twig', [
            'configs' => $configs,
            'recentLogs' => $recentLogs,
        ]);
    }
}
