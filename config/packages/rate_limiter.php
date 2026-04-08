<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'rate_limiter' => [
            'fulltext_domain' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:FULL_TEXT_RATE_LIMIT_REQUESTS)%',
                'interval' => '%env(int:FULL_TEXT_RATE_LIMIT_INTERVAL)% seconds',
            ],
        ],
    ]);
};
