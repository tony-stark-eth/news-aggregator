<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ToggleAlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/alerts/{id}/toggle', name: 'app_alerts_toggle', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('toggle_alert_rule', $token)) {
            if ($isHtmx) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
        }

        $rule = $this->alertRuleRepository->findById($id);
        if (! $rule instanceof AlertRule) {
            if ($isHtmx) {
                return new Response('Alert rule not found.', Response::HTTP_NOT_FOUND);
            }

            $this->controller->addFlash('error', 'Alert rule not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
        }

        $rule->setEnabled(! $rule->isEnabled());
        $this->alertRuleRepository->save($rule, flush: true);

        if ($isHtmx) {
            return $this->controller->render('alert/_toggle_badge.html.twig', [
                'rule' => $rule,
            ]);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
    }
}
