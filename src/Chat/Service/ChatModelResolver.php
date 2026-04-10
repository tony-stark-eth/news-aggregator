<?php

declare(strict_types=1);

namespace App\Chat\Service;

use App\Shared\AI\Service\ModelDiscoveryServiceInterface;
use App\Shared\AI\ValueObject\ModelId;

final readonly class ChatModelResolver implements ChatModelResolverInterface
{
    private const string FALLBACK_MODEL = 'openrouter/free';

    public function __construct(
        private ModelDiscoveryServiceInterface $modelDiscovery,
    ) {
    }

    public function resolveModel(): string
    {
        $models = $this->modelDiscovery->discoverToolCallingModels();
        $modelIds = array_map(
            static fn (ModelId $m): string => $m->value,
            $models->toArray(),
        );

        if ($modelIds === []) {
            return self::FALLBACK_MODEL;
        }

        $primary = $modelIds[0];
        \assert($primary !== '');

        return $primary;
    }
}
