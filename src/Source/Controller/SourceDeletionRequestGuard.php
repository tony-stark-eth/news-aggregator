<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SourceDeletionRequestGuard
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{user: User, isHtmx: bool, sourcesUrl: string}|Response
     */
    public function authorize(Request $request): array|Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        return [
            'user' => $user,
            'isHtmx' => $request->headers->has('HX-Request'),
            'sourcesUrl' => $this->urlGenerator->generate('app_sources'),
        ];
    }

    public function validateCsrf(Request $request, string $tokenId, bool $isHtmx, string $sourcesUrl): ?Response
    {
        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');

        if ($this->controller->isCsrfTokenValid($tokenId, $token)) {
            return null;
        }

        if ($isHtmx) {
            return SourceDeletionResponse::error($isHtmx, 'Invalid CSRF token.', $sourcesUrl);
        }

        $this->controller->addFlash('error', 'Invalid CSRF token.');

        return new RedirectResponse($sourcesUrl);
    }
}
