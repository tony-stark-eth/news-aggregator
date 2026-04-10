<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\TestNotificationController;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversNothing]
final class TestNotificationControllerTest extends TestCase
{
    public function testHtmxSuccessSendsNotificationAndReturnsBadge(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $controller = $this->buildController($notifier, 'telegram://bot@default');

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Sent successfully', (string) $response->getContent());
        self::assertStringContainsString('badge-success', (string) $response->getContent());
    }

    public function testHtmxNoTransportReturnsWarningBadge(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $controller = $this->buildController($notifier, 'null://null');

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('No notification transport configured', (string) $response->getContent());
        self::assertStringContainsString('badge-warning', (string) $response->getContent());
    }

    public function testHtmxEmptyDsnReturnsWarningBadge(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $controller = $this->buildController($notifier, '');

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertStringContainsString('No notification transport configured', (string) $response->getContent());
    }

    public function testHtmxDeliveryFailureReturnsErrorBadge(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')
            ->willThrowException(new \RuntimeException('Transport error'));

        $controller = $this->buildController($notifier, 'telegram://bot@default');

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Delivery failed', (string) $response->getContent());
        self::assertStringContainsString('badge-error', (string) $response->getContent());
    }

    public function testHtmxInvalidCsrfReturnsForbidden(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(new User('test@example.com', 'hashed'));
        $helper->method('isCsrfTokenValid')->willReturn(false);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $controller = new TestNotificationController($helper, $notifier, $urlGenerator, 'telegram://bot@default');

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'bad-token');

        $response = $controller($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testNonHtmxSuccessRedirectsWithFlash(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(new User('test@example.com', 'hashed'));
        $helper->method('isCsrfTokenValid')->willReturn(true);
        $helper->expects(self::once())->method('addFlash')->with('success', 'Sent successfully');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/settings');

        $controller = new TestNotificationController($helper, $notifier, $urlGenerator, 'telegram://bot@default');

        $request = new Request();
        $request->request->set('_token', 'valid');

        $response = $controller($request);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testUnauthenticatedUserRedirectsToLogin(): void
    {
        $notifier = $this->createStub(NotifierInterface::class);

        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');

        $controller = new TestNotificationController($helper, $notifier, $urlGenerator, 'telegram://bot@default');

        $response = $controller(new Request());

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    private function buildController(NotifierInterface $notifier, string $dsn): TestNotificationController
    {
        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(new User('test@example.com', 'hashed'));
        $helper->method('isCsrfTokenValid')->willReturn(true);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        return new TestNotificationController($helper, $notifier, $urlGenerator, $dsn);
    }
}
