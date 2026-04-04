<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\NotificationLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationLogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function index(): Response
    {
        /** @var list<NotificationLog> $logs */
        $logs = $this->entityManager
            ->getRepository(NotificationLog::class)
            ->createQueryBuilder('l')
            ->orderBy('l.sentAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('notification/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}
