<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('monolog', [
        'channels' => ['deprecation', 'app'],
    ]);

    if ($container->env() === 'dev') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'channels' => [
                        'elements' => ['!event'],
                    ],
                ],
                'rotating' => [
                    'type' => 'rotating_file',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                    'max_files' => 7,
                    'channels' => [
                        'elements' => ['!event'],
                    ],
                ],
                'app' => [
                    'type' => 'rotating_file',
                    'path' => '%kernel.logs_dir%/app.log',
                    'level' => 'info',
                    'max_files' => 30,
                    'channels' => [
                        'elements' => ['app'],
                    ],
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        'elements' => ['!event', '!doctrine', '!console'],
                    ],
                ],
            ],
        ]);
    }

    if ($container->env() === 'test') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => [
                        'elements' => ['!event'],
                    ],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ]);
    }

    if ($container->env() === 'prod') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => [
                        'elements' => ['!deprecation'],
                    ],
                    'buffer_size' => 50,
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'formatter' => 'monolog.formatter.json',
                ],
                'app' => [
                    'type' => 'rotating_file',
                    'path' => '%kernel.logs_dir%/app.log',
                    'level' => 'info',
                    'max_files' => 30,
                    'formatter' => 'monolog.formatter.json',
                    'channels' => [
                        'elements' => ['app'],
                    ],
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        'elements' => ['!event', '!doctrine'],
                    ],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => [
                        'elements' => ['deprecation'],
                    ],
                    'path' => 'php://stderr',
                    'formatter' => 'monolog.formatter.json',
                ],
            ],
        ]);
    }
};
