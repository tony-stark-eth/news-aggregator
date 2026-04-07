<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Controller;

use App\Source\Controller\ValidateFeedUrlController;
use App\Source\Exception\FeedFetchException;
use App\Source\Exception\InvalidFeedUrlException;
use App\Source\Service\FeedValidationServiceInterface;
use App\Source\ValueObject\FeedPreview;
use App\Source\ValueObject\FeedUrl;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class ValidateFeedUrlControllerTest extends TestCase
{
    /**
     * @var ControllerHelper&MockObject
     */
    private MockObject $controllerHelper;

    /**
     * @var FeedValidationServiceInterface&MockObject
     */
    private MockObject $feedValidation;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    private ValidateFeedUrlController $controller;

    protected function setUp(): void
    {
        $this->controllerHelper = $this->createMock(ControllerHelper::class);
        $this->feedValidation = $this->createMock(FeedValidationServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ValidateFeedUrlController(
            $this->controllerHelper,
            $this->feedValidation,
            $this->logger,
        );
    }

    public function testReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->controllerHelper->method('getUser')->willReturn(null);

        $request = new Request();
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testRendersErrorOnInvalidCsrfToken(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')
            ->with('validate_feed_url', '')
            ->willReturn(false);

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => $params['error'] === 'Invalid CSRF token.',
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['_validate_token' => '']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testRendersErrorOnEmptyUrl(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => $params['error'] === 'Please enter a feed URL.',
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => '', '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testRendersErrorOnInvalidFeedUrl(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $this->feedValidation->expects(self::once())
            ->method('validate')
            ->willThrowException(InvalidFeedUrlException::fromUrl('bad'));

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => str_contains($params['error'], 'Invalid URL format'),
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => 'bad', '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testRendersErrorOnFetchFailure(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $url = 'https://example.com/feed.xml';
        $this->feedValidation->expects(self::once())
            ->method('validate')
            ->willThrowException(FeedFetchException::fromUrl($url, 'timeout'));

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Feed validation fetch failed', self::callback(
                static fn (array $ctx): bool => $ctx['url'] === $url
                    && str_contains($ctx['reason'], 'timeout'),
            ));

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => str_contains($params['error'], 'Could not fetch'),
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => $url, '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testRendersErrorOnUnexpectedException(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $url = 'https://example.com/feed.xml';
        $this->feedValidation->expects(self::once())
            ->method('validate')
            ->willThrowException(new \RuntimeException('something broke'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Unexpected feed validation error', self::callback(
                static fn (array $ctx): bool => $ctx['url'] === $url
                    && $ctx['exception'] === 'something broke',
            ));

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => str_contains($params['error'], 'unexpected error'),
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => $url, '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testRendersPreviewOnSuccess(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $url = 'https://example.com/feed.xml';
        $preview = new FeedPreview(
            title: 'My Feed',
            itemCount: 5,
            detectedLanguage: 'de',
            feedUrl: new FeedUrl($url),
        );

        $this->feedValidation->expects(self::once())
            ->method('validate')
            ->with($url)
            ->willReturn($preview);

        $expectedResponse = new Response('preview html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => $params['preview'] === $preview,
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => $url, '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testWhitespaceOnlyUrlTreatedAsEmpty(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);

        $expectedResponse = new Response('error html');
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('source/_feed_preview.html.twig', self::callback(
                static fn (array $params): bool => $params['error'] === 'Please enter a feed URL.',
            ))
            ->willReturn($expectedResponse);

        $request = new Request(request: ['feed_url' => '   ', '_validate_token' => 'valid']);
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }
}
