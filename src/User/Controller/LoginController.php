<?php

declare(strict_types=1);

namespace App\User\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController
{
    public function __construct(
        private readonly ControllerHelper $controller,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function __invoke(AuthenticationUtils $authUtils): Response
    {
        return $this->controller->render('security/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error' => $authUtils->getLastAuthenticationError(),
        ]);
    }
}
