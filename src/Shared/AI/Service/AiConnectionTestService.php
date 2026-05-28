<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\Platform\OpenAiCompatibleUrlNormalizer;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class AiConnectionTestService implements AiConnectionTestServiceInterface
{
    public function testOpenAiCompatible(string $baseUrl, string $model, string $apiKey): string
    {
        $baseUrl = OpenAiCompatibleUrlNormalizer::normalize($baseUrl);
        $model = trim($model);
        $apiKey = trim($apiKey);

        if ($baseUrl === '') {
            throw new \InvalidArgumentException('Base URL is required.');
        }

        if ($model === '') {
            throw new \InvalidArgumentException('Model is required.');
        }

        if ($apiKey === '') {
            throw new \InvalidArgumentException('API key is required.');
        }

        $catalog = new ModelCatalog([
            $model => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
        ]);

        $platform = GenericPlatformFactory::create(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            modelCatalog: $catalog,
            supportsEmbeddings: false,
        );

        try {
            $messageBag = new MessageBag(Message::ofUser('Respond with exactly one word: hello'));
            $result = $platform->invoke($model, $messageBag);

            return trim($result->asText());
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
