<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Shared\Controller\DashboardController;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Repository\UserArticleReadRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class DashboardControllerTest extends TestCase
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
     * @var UserArticleReadRepositoryInterface&MockObject
     */
    private MockObject $userArticleReadRepository;

    /**
     * @var SourceRepositoryInterface&MockObject
     */
    private MockObject $sourceRepository;

    /**
     * @var CategoryRepositoryInterface&MockObject
     */
    private MockObject $categoryRepository;

    /**
     * @var NotificationLogRepositoryInterface&MockObject
     */
    private MockObject $notificationLogRepository;

    private MockClock $clock;

    private DashboardController $controller;

    protected function setUp(): void
    {
        $this->controllerHelper = $this->createMock(ControllerHelper::class);
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->userArticleReadRepository = $this->createMock(UserArticleReadRepositoryInterface::class);
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->notificationLogRepository = $this->createMock(NotificationLogRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-06 14:30:00', new \DateTimeZone('UTC')));

        $this->controller = new DashboardController(
            $this->controllerHelper,
            $this->articleRepository,
            $this->userArticleReadRepository,
            $this->sourceRepository,
            $this->categoryRepository,
            $this->notificationLogRepository,
            $this->clock,
        );
    }

    public function testDashboardPassesAlertsTodayToTemplate(): void
    {
        $this->articleRepository->method('findPaginated')->willReturn([]);
        $this->controllerHelper->method('getUser')->willReturn(null);
        $this->articleRepository->method('countSince')->willReturn(5);
        $this->sourceRepository->method('countEnabled')->willReturn(3);
        $this->notificationLogRepository->expects(self::once())
            ->method('countSentSince')
            ->with(new \DateTimeImmutable('2026-04-06 00:00:00', new \DateTimeZone('UTC')))
            ->willReturn(7);
        $this->sourceRepository->method('findMostRecentFetchedAt')
            ->willReturn(new \DateTimeImmutable('2026-04-06 13:00:00'));
        $this->categoryRepository->method('findAllOrderedByWeight')->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'dashboard/index.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertSame(7, $params['alertsToday']);
                    self::assertInstanceOf(\DateTimeImmutable::class, $params['lastFetchedAt']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        $request = new Request();
        $response = ($this->controller)($request);

        self::assertSame($expectedResponse, $response);
    }

    public function testDashboardPassesZeroAlertsWhenNoneExist(): void
    {
        $this->articleRepository->method('findPaginated')->willReturn([]);
        $this->controllerHelper->method('getUser')->willReturn(null);
        $this->articleRepository->method('countSince')->willReturn(0);
        $this->sourceRepository->method('countEnabled')->willReturn(1);
        $this->notificationLogRepository->method('countSentSince')->willReturn(0);
        $this->sourceRepository->method('findMostRecentFetchedAt')->willReturn(null);
        $this->categoryRepository->method('findAllOrderedByWeight')->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'dashboard/index.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertSame(0, $params['alertsToday']);
                    self::assertNull($params['lastFetchedAt']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        $request = new Request();
        ($this->controller)($request);
    }

    public function testHtmxRequestDoesNotQueryStats(): void
    {
        $this->articleRepository->method('findPaginated')->willReturn([]);
        $this->controllerHelper->method('getUser')->willReturn(null);

        $this->notificationLogRepository->expects(self::never())->method('countSentSince');
        $this->sourceRepository->expects(self::never())->method('findMostRecentFetchedAt');

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with('dashboard/_article_list.html.twig', self::anything())
            ->willReturn($expectedResponse);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');

        ($this->controller)($request);
    }
}
