<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Service\SettingsServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class NavigationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SettingsServiceInterface $settingsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return [
            'nav' => [
                'activeRoute' => $request?->attributes->getString('_route', ''),
                'searchQuery' => $request?->query->getString('q', ''),
                'sentimentSlider' => $this->settingsService->getSentimentSlider(),
            ],
        ];
    }
}
