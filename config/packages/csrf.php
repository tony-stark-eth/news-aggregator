<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// Enable stateless CSRF protection for forms and logins/logouts
return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'form' => [
            'csrf_protection' => [
                'token_id' => 'submit',
            ],
        ],
        'csrf_protection' => [
            'stateless_token_ids' => ['submit', 'authenticate', 'logout', 'mark_all_read', 'delete_alert_rule', 'delete_source'],
        ],
    ]);
};
