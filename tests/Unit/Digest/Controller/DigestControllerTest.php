<?php

declare(strict_types=1);

namespace App\Tests\Unit\Digest\Controller;

use App\Digest\Controller\DigestController;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Digest\Repository\DigestLogRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class DigestControllerTest extends TestCase
{
    /**
     * @var ControllerHelper&MockObject
     */
    private MockObject $controllerHelper;

    /**
     * @var DigestConfigRepositoryInterface&MockObject
     */
    private MockObject $configRepository;

    /**
     * @var DigestLogRepositoryInterface&MockObject
     */
    private MockObject $logRepository;

    private DigestController $controller;

    protected function setUp(): void
    {
        $this->controllerHelper = $this->createMock(ControllerHelper::class);
        $this->configRepository = $this->createMock(DigestConfigRepositoryInterface::class);
        $this->logRepository = $this->createMock(DigestLogRepositoryInterface::class);

        $this->controller = new DigestController(
            $this->controllerHelper,
            $this->configRepository,
            $this->logRepository,
        );
    }

    public function testNoStatusFilterPassesNullToRepository(): void
    {
        $this->configRepository->method('findAll')->willReturn([]);
        $this->logRepository->expects(self::once())
            ->method('findRecent')
            ->with(20, null)
            ->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'digest/index.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertNull($params['statusFilter']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        ($this->controller)();
    }

    public function testSuccessStatusFilterPassesTrueToRepository(): void
    {
        $this->configRepository->method('findAll')->willReturn([]);
        $this->logRepository->expects(self::once())
            ->method('findRecent')
            ->with(20, true)
            ->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'digest/index.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertSame('success', $params['statusFilter']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        ($this->controller)(status: 'success');
    }

    public function testFailedStatusFilterPassesFalseToRepository(): void
    {
        $this->configRepository->method('findAll')->willReturn([]);
        $this->logRepository->expects(self::once())
            ->method('findRecent')
            ->with(20, false)
            ->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->expects(self::once())
            ->method('render')
            ->with(
                'digest/index.html.twig',
                self::callback(static function (array $params): bool {
                    self::assertSame('failed', $params['statusFilter']);

                    return true;
                }),
            )
            ->willReturn($expectedResponse);

        ($this->controller)(status: 'failed');
    }

    public function testUnknownStatusFilterPassesNullToRepository(): void
    {
        $this->configRepository->method('findAll')->willReturn([]);
        $this->logRepository->expects(self::once())
            ->method('findRecent')
            ->with(20, null)
            ->willReturn([]);

        $expectedResponse = new Response();
        $this->controllerHelper->method('render')->willReturn($expectedResponse);

        ($this->controller)(status: 'unknown');
    }
}
