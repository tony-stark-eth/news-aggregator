<?php

declare(strict_types=1);

namespace App\User\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class LogoutController
{
    #[Route('/logout', name: 'app_logout')]
    public function __invoke(): never
    {
        throw new \LogicException('This should be intercepted by the logout key on the firewall.');
    }
}
