<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('mercure', [
        'hubs' => [
            'default' => [
                'url' => '%env(default::MERCURE_URL)%',
                'public_url' => '%env(default::MERCURE_PUBLIC_URL)%',
                'jwt' => [
                    'secret' => '%env(default::MERCURE_JWT_SECRET)%',
                    'publish' => ['*'],
                ],
            ],
        ],
    ]);
};
