<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Source\Entity\Source;
use App\User\Controller\ToggleBookmarkController;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversNothing]
final class ToggleBookmarkControllerTest extends TestCase
{
    /**
     * @var ControllerHelper&MockObject
     */
    private MockObject $controllerHelper;

    /**
     * @var ArticleRepositoryInterface&MockObject
     */
    private MockObject $articleRepository;

    /**
     * @var UserArticleBookmarkRepositoryInterface&MockObject
     */
    private MockObject $bookmarkRepository;

    private MockClock $clock;

    /**
     * @var UrlGeneratorInterface&MockObject
     */
    private MockObject $urlGenerator;

    private ToggleBookmarkController $controller;

    protected function setUp(): void
    {
        $this->controllerHelper = $this->createMock(ControllerHelper::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->bookmarkRepository = $this->createMock(UserArticleBookmarkRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-07 12:00:00', new \DateTimeZone('UTC')));
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->controller = new ToggleBookmarkController(
            $this->controllerHelper,
            $this->articleRepository,
            $this->bookmarkRepository,
            $this->clock,
            $this->urlGenerator,
        );
    }

    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->controllerHelper->method('getUser')->willReturn(null);
        $this->urlGenerator->method('generate')->willReturn('/login');

        $request = new Request();
        $response = ($this->controller)($request, 1);

        self::assertSame(302, $response->getStatusCode());
    }

    public function testReturns403ForInvalidCsrfTokenHtmx(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(false);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $response = ($this->controller)($request, 1);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRedirectsOnInvalidCsrfTokenNonHtmx(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(false);
        $this->controllerHelper->expects(self::once())->method('addFlash')->with('error', 'Invalid CSRF token.');
        $this->urlGenerator->method('generate')->willReturn('/');

        $request = new Request();
        $response = ($this->controller)($request, 1);

        self::assertSame(302, $response->getStatusCode());
    }

    public function testReturns404WhenArticleNotFoundHtmx(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn(null);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $response = ($this->controller)($request, 999);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreatesBookmarkWhenNotExisting(): void
    {
        $user = new User('test@example.com', 'hashed');
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());

        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn($article);
        $this->bookmarkRepository->method('findByUserAndArticle')->willReturn(null);

        $this->bookmarkRepository->expects(self::once())->method('save');
        $this->bookmarkRepository->expects(self::never())->method('remove');

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'components/_bookmark_button.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertTrue($params['isBookmarked']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $response = ($this->controller)($request, 1);

        self::assertSame($expectedResponse, $response);
    }

    public function testRemovesBookmarkWhenAlreadyExisting(): void
    {
        $user = new User('test@example.com', 'hashed');
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());
        $existing = new UserArticleBookmark($user, $article, new \DateTimeImmutable());

        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn($article);
        $this->bookmarkRepository->method('findByUserAndArticle')->willReturn($existing);

        $this->bookmarkRepository->expects(self::once())->method('remove')->with($existing, true);
        $this->bookmarkRepository->expects(self::never())->method('save');

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'components/_bookmark_button.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertFalse($params['isBookmarked']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $response = ($this->controller)($request, 1);

        self::assertSame($expectedResponse, $response);
    }

    public function testNonHtmxBookmarkRedirectsWithFlash(): void
    {
        $user = new User('test@example.com', 'hashed');
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());

        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn($article);
        $this->bookmarkRepository->method('findByUserAndArticle')->willReturn(null);
        $this->bookmarkRepository->method('save');

        $this->controllerHelper->expects(self::once())
            ->method('addFlash')
            ->with('success', 'Article bookmarked.');
        $this->urlGenerator->method('generate')->willReturn('/');

        $request = new Request();
        $response = ($this->controller)($request, 1);

        self::assertSame(302, $response->getStatusCode());
    }

    public function testNonHtmxRemoveBookmarkRedirectsWithFlash(): void
    {
        $user = new User('test@example.com', 'hashed');
        $source = $this->createStub(Source::class);
        $article = new Article('Test', 'https://example.com/1', $source, new \DateTimeImmutable());
        $existing = new UserArticleBookmark($user, $article, new \DateTimeImmutable());

        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn($article);
        $this->bookmarkRepository->method('findByUserAndArticle')->willReturn($existing);

        $this->controllerHelper->expects(self::once())
            ->method('addFlash')
            ->with('success', 'Bookmark removed.');
        $this->urlGenerator->method('generate')->willReturn('/');

        $request = new Request();
        $response = ($this->controller)($request, 1);

        self::assertSame(302, $response->getStatusCode());
    }

    public function testRedirectsOnArticleNotFoundNonHtmx(): void
    {
        $user = new User('test@example.com', 'hashed');
        $this->controllerHelper->method('getUser')->willReturn($user);
        $this->controllerHelper->method('isCsrfTokenValid')->willReturn(true);
        $this->articleRepository->method('findById')->willReturn(null);
        $this->controllerHelper->expects(self::once())->method('addFlash')->with('error', 'Article not found.');
        $this->urlGenerator->method('generate')->willReturn('/');

        $request = new Request();
        $response = ($this->controller)($request, 999);

        self::assertSame(302, $response->getStatusCode());
    }
}
