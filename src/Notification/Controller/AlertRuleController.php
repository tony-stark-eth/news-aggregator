<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Repository\AlertRuleRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
    ) {
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function __invoke(): Response
    {
        return $this->controller->render('alert/index.html.twig', [
            'rules' => $this->alertRuleRepository->findAll(),
        ]);
    }
}
