<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TestNotificationController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly NotifierInterface $notifier,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $notifierDsn,
    ) {
    }

    #[Route('/settings/test-notification', name: 'app_settings_test_notification', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');
        if (! $this->controller->isCsrfTokenValid('test_notification', $token)) {
            return $this->respond($isHtmx, 'error', 'Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        if (! $this->hasTransport()) {
            return $this->respond($isHtmx, 'warning', 'No notification transport configured');
        }

        return $this->sendTestNotification($isHtmx);
    }

    private function sendTestNotification(bool $isHtmx): Response
    {
        try {
            $notification = new Notification('Test notification from News Aggregator', ['chat']);
            $notification->content('This is a test notification to verify your notification transport is working correctly.');
            $this->notifier->send($notification);
        } catch (\Throwable) {
            return $this->respond($isHtmx, 'error', 'Delivery failed');
        }

        return $this->respond($isHtmx, 'success', 'Sent successfully');
    }

    private function respond(bool $isHtmx, string $level, string $message, int $statusCode = Response::HTTP_OK): Response
    {
        if ($isHtmx) {
            $badgeClass = match ($level) {
                'success' => 'badge-success',
                'warning' => 'badge-warning',
                default => 'badge-error',
            };

            return new Response(
                sprintf('<span class="badge %s badge-sm">%s</span>', $badgeClass, $message),
                $statusCode,
            );
        }

        $this->controller->addFlash($level, $message);

        return new RedirectResponse($this->urlGenerator->generate('app_settings'));
    }

    private function hasTransport(): bool
    {
        return $this->notifierDsn !== '' && $this->notifierDsn !== 'null://null';
    }
}
