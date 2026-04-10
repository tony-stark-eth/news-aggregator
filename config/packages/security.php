<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // https://symfony.com/doc/current/security.html#registering-the-user-hashing-passwords
    // https://symfony.com/doc/current/security.html#loading-the-user-the-user-provider
    $container->extension('security', [
        'password_hashers' => [
            'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                'algorithm' => 'auto',
            ],
        ],
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => 'App\User\Entity\User',
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_profiler|_wdt|assets|build)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                ],
                'logout' => [
                    'path' => 'app_logout',
                ],
            ],
        ],
        // Note: Only the *first* matching rule is applied
        'access_control' => [
            [
                'path' => '^/health$',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/login',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/',
                'roles' => 'ROLE_ADMIN',
            ],
        ],
    ]);

    if ($container->env() === 'test') {
        // Password hashers are resource-intensive by design to ensure security.
        // In tests, it's safe to reduce their cost to improve performance.
        $container->extension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                    'algorithm' => 'auto',
                    'cost' => 4,       // Lowest possible value for bcrypt
                    'time_cost' => 3,  // Lowest possible value for argon
                    'memory_cost' => 10, // Lowest possible value for argon
                ],
            ],
        ]);
    }
};
