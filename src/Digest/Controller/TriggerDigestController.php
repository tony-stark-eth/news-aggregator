<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestConfig;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TriggerDigestController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/digests/{id}/trigger', name: 'app_digests_trigger', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $token = $this->requestStack->getCurrentRequest()?->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('trigger_digest', $token)) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $config = $this->digestConfigRepository->findById($id);
        if (! $config instanceof DigestConfig) {
            $this->controller->addFlash('error', 'Digest configuration not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        /** @var int $configId */
        $configId = $config->getId();
        $this->messageBus->dispatch(new GenerateDigestMessage($configId, force: true));

        $this->controller->addFlash('success', 'Digest generation triggered. It will appear in history shortly.');

        return new RedirectResponse($this->urlGenerator->generate('app_digests'));
    }
}
