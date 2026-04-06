<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EditAlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/alerts/{id}/edit', name: 'app_alerts_edit', methods: ['GET', 'POST'])]
    public function __invoke(int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $rule = $this->alertRuleRepository->findById($id);
        if (! $rule instanceof AlertRule) {
            $this->controller->addFlash('error', 'Alert rule not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request, $rule, $id);
        }

        return $this->controller->render('alert/edit.html.twig', [
            'rule' => $rule,
        ]);
    }

    private function handlePost(
        Request $request,
        AlertRule $rule,
        int $id,
    ): RedirectResponse {
        $name = trim((string) $request->request->get('name'));
        $typeValue = (string) $request->request->get('type');
        $keywordsRaw = trim((string) $request->request->get('keywords'));
        $urgencyValue = (string) $request->request->get('urgency');
        $contextPrompt = trim((string) $request->request->get('context_prompt'));
        $severityThreshold = (int) $request->request->get('severity_threshold', 5);
        $cooldownMinutes = (int) $request->request->get('cooldown_minutes', 60);

        if ($name === '') {
            $this->controller->addFlash('error', 'Name is required.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts_edit', [
                'id' => $id,
            ]));
        }

        $type = AlertRuleType::tryFrom($typeValue);
        if ($type === null) {
            $this->controller->addFlash('error', 'Invalid alert type.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts_edit', [
                'id' => $id,
            ]));
        }

        $urgency = AlertUrgency::tryFrom($urgencyValue) ?? AlertUrgency::Medium;
        $keywords = $this->parseKeywords($keywordsRaw);

        $rule->setName($name);
        $rule->setType($type);
        $rule->setKeywords($keywords);
        $rule->setUrgency($urgency);
        $rule->setSeverityThreshold($severityThreshold);
        $rule->setCooldownMinutes($cooldownMinutes);
        $rule->setContextPrompt($contextPrompt !== '' ? $contextPrompt : null);
        $rule->setUpdatedAt($this->clock->now());

        $this->alertRuleRepository->save($rule, flush: true);

        $this->controller->addFlash('success', 'Alert rule updated.');

        return new RedirectResponse($this->urlGenerator->generate('app_alerts'));
    }

    /**
     * @return list<string>
     */
    private function parseKeywords(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map(trim(...), explode(',', $raw)),
                static fn (string $k): bool => $k !== '',
            ),
        );
    }
}
