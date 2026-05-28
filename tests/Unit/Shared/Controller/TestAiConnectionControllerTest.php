<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\AI\Service\AiConnectionTestServiceInterface;
use App\Shared\Controller\TestAiConnectionController;
use App\Shared\Service\SettingsServiceInterface;
use App\User\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(TestAiConnectionController::class)]
final class TestAiConnectionControllerTest extends TestCase
{
    public function testHtmxSuccessReturnsBadgeWithModelResponse(): void
    {
        $connectionTest = $this->createMock(AiConnectionTestServiceInterface::class);
        $connectionTest->expects(self::once())
            ->method('testOpenAiCompatible')
            ->with('http://vllm.local/v1', 'test-model', 'secret')
            ->willReturn('hello');

        $settings = $this->createStub(SettingsServiceInterface::class);

        $controller = $this->buildController($connectionTest, $settings);

        $request = new Request([], [
            'ai_openai_base_url' => 'http://vllm.local/v1',
            'ai_openai_model' => 'test-model',
            'ai_openai_api_key' => 'secret',
        ]);
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Connected', (string) $response->getContent());
        self::assertStringContainsString('hello', (string) $response->getContent());
        self::assertStringContainsString('badge-success', (string) $response->getContent());
    }

    public function testHtmxUsesSavedApiKeyWhenFieldEmpty(): void
    {
        $connectionTest = $this->createMock(AiConnectionTestServiceInterface::class);
        $connectionTest->expects(self::once())
            ->method('testOpenAiCompatible')
            ->with('http://vllm.local', 'test-model', 'saved-key')
            ->willReturn('ok');

        $settings = $this->createMock(SettingsServiceInterface::class);
        $settings->method('getOpenAiApiKey')->willReturn('saved-key');

        $controller = $this->buildController($connectionTest, $settings);

        $request = new Request([], [
            'ai_openai_base_url' => 'http://vllm.local',
            'ai_openai_model' => 'test-model',
            'ai_openai_api_key' => '',
        ]);
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertStringContainsString('badge-success', (string) $response->getContent());
    }

    public function testHtmxMissingFieldsReturnsWarningBadge(): void
    {
        $connectionTest = $this->createMock(AiConnectionTestServiceInterface::class);
        $connectionTest->expects(self::never())->method('testOpenAiCompatible');

        $settings = $this->createStub(SettingsServiceInterface::class);

        $controller = $this->buildController($connectionTest, $settings);

        $request = new Request([], [
            'ai_openai_base_url' => '',
            'ai_openai_model' => '',
        ]);
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertStringContainsString('Base URL and model are required', (string) $response->getContent());
        self::assertStringContainsString('badge-warning', (string) $response->getContent());
    }

    public function testHtmxConnectionFailureReturnsErrorBadge(): void
    {
        $connectionTest = $this->createMock(AiConnectionTestServiceInterface::class);
        $connectionTest->expects(self::once())
            ->method('testOpenAiCompatible')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $settings = $this->createStub(SettingsServiceInterface::class);

        $controller = $this->buildController($connectionTest, $settings);

        $request = new Request([], [
            'ai_openai_base_url' => 'http://vllm.local',
            'ai_openai_model' => 'test-model',
            'ai_openai_api_key' => 'secret',
        ]);
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'valid');

        $response = $controller($request);

        self::assertStringContainsString('Connection failed', (string) $response->getContent());
        self::assertStringContainsString('badge-error', (string) $response->getContent());
    }

    public function testHtmxInvalidCsrfReturnsForbidden(): void
    {
        $connectionTest = $this->createStub(AiConnectionTestServiceInterface::class);
        $settings = $this->createStub(SettingsServiceInterface::class);

        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(new User('test@example.com', 'hashed'));
        $helper->method('isCsrfTokenValid')->willReturn(false);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $controller = new TestAiConnectionController($helper, $connectionTest, $settings, $urlGenerator);

        $request = new Request();
        $request->headers->set('HX-Request', 'true');
        $request->headers->set('X-CSRF-Token', 'bad-token');

        $response = $controller($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testUnauthenticatedUserRedirectsToLogin(): void
    {
        $connectionTest = $this->createStub(AiConnectionTestServiceInterface::class);
        $settings = $this->createStub(SettingsServiceInterface::class);

        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/login');

        $controller = new TestAiConnectionController($helper, $connectionTest, $settings, $urlGenerator);

        $response = $controller(new Request());

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    private function buildController(
        AiConnectionTestServiceInterface $connectionTest,
        SettingsServiceInterface $settings,
    ): TestAiConnectionController {
        $helper = $this->createMock(ControllerHelper::class);
        $helper->method('getUser')->willReturn(new User('test@example.com', 'hashed'));
        $helper->method('isCsrfTokenValid')->willReturn(true);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        return new TestAiConnectionController($helper, $connectionTest, $settings, $urlGenerator);
    }
}
