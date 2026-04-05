<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\AlertRule;
use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateAlertRuleController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/alerts/new', name: 'app_alerts_new', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request, $user);
        }

        return $this->controller->render('alert/new.html.twig');
    }

    private function handlePost(
        Request $request,
        User $user,
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

            return new RedirectResponse($this->urlGenerator->generate('app_alerts_new'));
        }

        $type = AlertRuleType::tryFrom($typeValue);
        if ($type === null) {
            $this->controller->addFlash('error', 'Invalid alert type.');

            return new RedirectResponse($this->urlGenerator->generate('app_alerts_new'));
        }

        $urgency = AlertUrgency::tryFrom($urgencyValue) ?? AlertUrgency::Medium;
        $keywords = $this->parseKeywords($keywordsRaw);

        $rule = new AlertRule($name, $type, $user, $this->clock->now());
        $rule->setKeywords($keywords);
        $rule->setUrgency($urgency);
        $rule->setSeverityThreshold($severityThreshold);
        $rule->setCooldownMinutes($cooldownMinutes);

        if ($contextPrompt !== '') {
            $rule->setContextPrompt($contextPrompt);
        }

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $this->controller->addFlash('success', 'Alert rule created.');

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
