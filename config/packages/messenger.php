<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'messenger' => [
            // Uncomment to send failed messages to this transport for later handling:
            // 'failure_transport' => 'failed',
            'transports' => [
                'async' => [
                    'dsn' => 'doctrine://default',
                    'retry_strategy' => [
                        'max_retries' => 3,
                        'multiplier' => 2,
                        'delay' => 1000,
                        'max_delay' => 0,
                    ],
                ],
            ],
            'routing' => [
                'App\Source\Message\FetchSourceMessage' => 'async',
                'App\Notification\Message\SendNotificationMessage' => 'async',
                'App\Digest\Message\GenerateDigestMessage' => 'async',
                'App\Article\Message\RescoreArticlesMessage' => 'async',
            ],
        ],
    ]);

    // The scheduler_fetch transport is auto-registered by #[AsSchedule('fetch')]
    // on FetchScheduleProvider — no manual config needed.
};
