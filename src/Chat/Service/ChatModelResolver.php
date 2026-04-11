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
        private string $paidFallbackModel = '',
    ) {
    }

    public function resolveModel(): string
    {
        $chain = $this->resolveModelChain();

        return $chain[0] ?? self::FALLBACK_MODEL;
    }

    /**
     * @return list<non-empty-string>
     */
    public function resolveModelChain(): array
    {
        $models = $this->modelDiscovery->discoverToolCallingModels();

        /** @var list<non-empty-string> $modelIds */
        $modelIds = array_map(
            static fn (ModelId $m): string => $m->value,
            $models->toArray(),
        );

        if ($modelIds === []) {
            $modelIds = [self::FALLBACK_MODEL];
        }

        // Append paid fallback as last resort
        if ($this->paidFallbackModel !== '') {
            $modelIds[] = $this->paidFallbackModel;
        }

        return $modelIds;
    }
}
