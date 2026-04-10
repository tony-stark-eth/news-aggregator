<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return new JsonResponse([
                'status' => 'ok',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Health check failed: {error}', [
                'error' => $exception->getMessage(),
            ]);

            return new JsonResponse(
                [
                    'status' => 'error',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
