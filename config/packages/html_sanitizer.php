<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'html_sanitizer' => [
            'sanitizers' => [
                'app.fulltext_sanitizer' => [
                    'allow_safe_elements' => true,
                    'block_elements' => ['script', 'style', 'iframe', 'object', 'embed', 'form'],
                ],
            ],
        ],
    ]);
};
