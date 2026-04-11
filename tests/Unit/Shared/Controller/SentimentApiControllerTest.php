<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\SentimentApiController;
use App\Shared\Service\SettingsService;
use App\Shared\Service\SettingsServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SentimentApiController::class)]
final class SentimentApiControllerTest extends TestCase
{
    public function testValidValuePersistsAndReturnsJson(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::once())
            ->method('set')
            ->with(SettingsService::KEY_SENTIMENT_SLIDER, '5');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '5',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"value":5}', $response->getContent());
    }

    public function testZeroValueAccepted(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::once())
            ->method('set')
            ->with(SettingsService::KEY_SENTIMENT_SLIDER, '0');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '0',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testNegativeValueAccepted(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::once())
            ->method('set')
            ->with(SettingsService::KEY_SENTIMENT_SLIDER, '-7');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '-7',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testValueTooHighReturnsBadRequest(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::never())->method('set');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '11',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testValueTooLowReturnsBadRequest(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::never())->method('set');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '-11',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testBoundaryMinus10Accepted(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::once())->method('set');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '-10',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testBoundaryPlus10Accepted(): void
    {
        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->expects(self::once())->method('set');

        $controller = new SentimentApiController($settings);
        $request = Request::create('/api/settings/sentiment', 'POST', [
            'value' => '10',
        ]);

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
