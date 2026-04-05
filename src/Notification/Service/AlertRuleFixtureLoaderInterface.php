<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Dto\AlertRuleFixture;

interface AlertRuleFixtureLoaderInterface
{
    /**
     * @return list<AlertRuleFixture>
     */
    public function loadFromPath(string $path): array;
}
