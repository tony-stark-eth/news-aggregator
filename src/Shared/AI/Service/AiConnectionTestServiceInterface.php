<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

interface AiConnectionTestServiceInterface
{
    /**
     * Sends a minimal prompt to verify OpenAI-compatible API connectivity.
     *
     * @throws \InvalidArgumentException when required fields are missing
     * @throws \RuntimeException when the API call fails
     */
    public function testOpenAiCompatible(string $baseUrl, string $model, string $apiKey): string;
}
