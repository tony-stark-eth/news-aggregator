<?php

declare(strict_types=1);

namespace App\Digest\Controller;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\User\Entity\User;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EditDigestConfigController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/digests/{id}/edit', name: 'app_digests_edit', methods: ['GET', 'POST'])]
    public function __invoke(int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $config = $this->digestConfigRepository->findById($id);
        if (! $config instanceof DigestConfig) {
            $this->controller->addFlash('error', 'Digest configuration not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request, $config, $id);
        }

        return $this->controller->render('digest/edit.html.twig', [
            'config' => $config,
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    private function handlePost(
        Request $request,
        DigestConfig $config,
        int $id,
    ): RedirectResponse {
        $name = trim((string) $request->request->get('name'));
        $schedule = trim((string) $request->request->get('schedule'));

        if ($name === '') {
            $this->controller->addFlash('error', 'Name is required.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests_edit', [
                'id' => $id,
            ]));
        }

        if ($schedule === '') {
            $this->controller->addFlash('error', 'Schedule is required.');

            return new RedirectResponse($this->urlGenerator->generate('app_digests_edit', [
                'id' => $id,
            ]));
        }

        /** @var list<string> $categories */
        $categories = $request->request->all('categories');
        $articleLimit = (int) $request->request->get('article_limit', 10);
        $enabled = $request->request->getBoolean('enabled');

        $config->setName($name);
        $config->setSchedule($schedule);
        $config->setCategories($categories);
        $config->setArticleLimit($articleLimit);
        $config->setEnabled($enabled);
        $config->setUpdatedAt($this->clock->now());

        $this->digestConfigRepository->save($config, flush: true);

        $this->controller->addFlash('success', 'Digest configuration updated.');

        return new RedirectResponse($this->urlGenerator->generate('app_digests'));
    }
}
