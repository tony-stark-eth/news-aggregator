<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('cmsig_seal', [
        'schemas' => [
            'articles' => [
                'dir' => '%kernel.project_dir%/config/seal',
                'engine' => 'loupe',
            ],
        ],
        'engines' => [
            'loupe' => [
                'adapter' => 'loupe://%kernel.project_dir%/var/loupe',
            ],
        ],
    ]);
};
