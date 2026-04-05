<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Repository\NotificationLogRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationLogController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly NotificationLogRepositoryInterface $notificationLogRepository,
    ) {
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function __invoke(): Response
    {
        return $this->controller->render('notification/index.html.twig', [
            'logs' => $this->notificationLogRepository->findRecent(100),
        ]);
    }
}
