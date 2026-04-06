<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
            'server_version' => '17',
            'profiling_collect_backtrace' => '%kernel.debug%',
        ],
        'orm' => [
            'validate_xml_mapping' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
            'identity_generation_preferences' => [
                PostgreSQLPlatform::class => 'identity',
            ],
            'auto_mapping' => true,
            'mappings' => [
                'Shared' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Shared/Entity',
                    'prefix' => 'App\Shared\Entity',
                    'alias' => 'Shared',
                ],
                'SharedAI' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Shared/AI/Entity',
                    'prefix' => 'App\Shared\AI\Entity',
                    'alias' => 'SharedAI',
                ],
                'Article' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Article/Entity',
                    'prefix' => 'App\Article\Entity',
                    'alias' => 'Article',
                ],
                'Source' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Source/Entity',
                    'prefix' => 'App\Source\Entity',
                    'alias' => 'Source',
                ],
                'User' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/User/Entity',
                    'prefix' => 'App\User\Entity',
                    'alias' => 'User',
                ],
                'Notification' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Notification/Entity',
                    'prefix' => 'App\Notification\Entity',
                    'alias' => 'Notification',
                ],
                'Digest' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Digest/Entity',
                    'prefix' => 'App\Digest\Entity',
                    'alias' => 'Digest',
                ],
            ],
        ],
    ]);

    if ($container->env() === 'test') {
        // "TEST_TOKEN" is typically set by ParaTest
        $container->extension('doctrine', [
            'dbal' => [
                'dbname_suffix' => '_test%env(default::TEST_TOKEN)%',
            ],
        ]);
    }

    if ($container->env() === 'prod') {
        $container->extension('doctrine', [
            'orm' => [
                'query_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.system_cache_pool',
                ],
                'result_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.result_cache_pool',
                ],
            ],
        ]);

        $container->extension('framework', [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                    'doctrine.system_cache_pool' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ]);
    }
};
