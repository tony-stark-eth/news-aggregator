<?php

declare(strict_types=1);

namespace App\Shared\AI\Service;

use App\Shared\AI\ValueObject\ModelQualityStats;
use App\Shared\AI\ValueObject\ModelQualityStatsMap;
use Psr\Cache\CacheItemPoolInterface;

final class ModelQualityTracker
{
    private const string CACHE_PREFIX = 'model_quality_';

    private const int CACHE_TTL = 604800; // 7 days

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function recordAcceptance(string $modelId): void
    {
        $this->updateStats($modelId, accepted: true);
    }

    public function recordRejection(string $modelId): void
    {
        $this->updateStats($modelId, accepted: false);
    }

    public function getStats(string $modelId): ModelQualityStats
    {
        $data = $this->loadStats($modelId);

        $total = $data['accepted'] + $data['rejected'];
        $rate = $total > 0 ? $data['accepted'] / $total : 0.0;

        return new ModelQualityStats(
            accepted: $data['accepted'],
            rejected: $data['rejected'],
            acceptanceRate: round($rate, 4),
        );
    }

    public function getAllStats(): ModelQualityStatsMap
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . 'index');
        /** @var list<string> $modelIds */
        $modelIds = $item->isHit() ? $item->get() : [];

        $stats = [];
        foreach ($modelIds as $modelId) {
            $stats[$modelId] = $this->getStats($modelId);
        }

        return new ModelQualityStatsMap($stats);
    }

    private function updateStats(string $modelId, bool $accepted): void
    {
        $data = $this->loadStats($modelId);

        if ($accepted) {
            $data['accepted']++;
        } else {
            $data['rejected']++;
        }

        $item = $this->cache->getItem(self::CACHE_PREFIX . md5($modelId));
        $item->set($data);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        $this->addToIndex($modelId);
    }

    /**
     * @return array{accepted: int, rejected: int}
     */
    private function loadStats(string $modelId): array
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . md5($modelId));

        if ($item->isHit()) {
            /** @var array{accepted: int, rejected: int} */
            return $item->get();
        }

        return [
            'accepted' => 0,
            'rejected' => 0,
        ];
    }

    private function addToIndex(string $modelId): void
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . 'index');
        /** @var list<string> $modelIds */
        $modelIds = $item->isHit() ? $item->get() : [];

        if (! in_array($modelId, $modelIds, true)) {
            $modelIds[] = $modelId;
            $item->set($modelIds);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }
    }
}
