<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function __invoke(): Response
    {
        /** @var list<AlertRule> $rules */
        $rules = $this->entityManager->getRepository(AlertRule::class)->findAll();

        return $this->controller->render('alert/index.html.twig', [
            'rules' => $rules,
        ]);
    }
}
