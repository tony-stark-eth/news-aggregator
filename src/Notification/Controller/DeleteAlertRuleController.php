<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteAlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/alerts/{id}/delete', name: 'app_alerts_delete', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $this->requestStack->getCurrentRequest()?->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('delete_alert_rule', $token)) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
        }

        $rule = $this->entityManager->find(AlertRule::class, $id);
        if ($rule === null) {
            $this->controller->addFlash('error', 'Alert rule not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
        }

        $this->entityManager->remove($rule);
        $this->entityManager->flush();

        $this->controller->addFlash('success', 'Alert rule deleted.');

        return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
    }
}
