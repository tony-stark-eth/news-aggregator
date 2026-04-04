<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlertRuleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function index(): Response
    {
        /** @var list<AlertRule> $rules */
        $rules = $this->entityManager->getRepository(AlertRule::class)->findAll();

        return $this->render('alert/index.html.twig', [
            'rules' => $rules,
        ]);
    }
}
