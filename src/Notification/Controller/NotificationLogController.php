<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Notification\ValueObject\DeliveryStatus;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationLogController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly NotificationLogRepositoryInterface $notificationLogRepository,
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
    ) {
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function __invoke(
        Request $request,
        #[MapQueryParameter]
        ?int $rule = null,
        #[MapQueryParameter]
        ?string $status = null,
    ): Response {
        $deliveryStatus = DeliveryStatus::tryFrom($status ?? '');

        $logs = $this->notificationLogRepository->findRecent(100, $rule, $deliveryStatus);

        $isHtmx = $request->headers->has('HX-Request');

        $templateData = [
            'logs' => $logs,
            'alertRules' => $this->alertRuleRepository->findAll(),
            'ruleFilter' => $rule,
            'statusFilter' => $status,
        ];

        if ($isHtmx) {
            return $this->controller->render('notification/_log_table.html.twig', $templateData);
        }

        return $this->controller->render('notification/index.html.twig', $templateData);
    }
}
