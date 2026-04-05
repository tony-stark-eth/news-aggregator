<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/sources/new', name: 'app_sources_new', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        return $this->renderForm();
    }

    private function handlePost(Request $request): RedirectResponse
    {
        $name = trim((string) $request->request->get('name'));
        $feedUrl = trim((string) $request->request->get('feed_url'));
        $siteUrl = trim((string) $request->request->get('site_url'));
        $categoryId = (int) $request->request->get('category_id');
        $language = trim((string) $request->request->get('language'));
        $enabled = $request->request->getBoolean('enabled');

        if ($name === '' || $feedUrl === '') {
            $this->controller->addFlash('error', 'Name and Feed URL are required.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $category = $this->categoryRepository->findById($categoryId);
        if ($category === null) {
            $this->controller->addFlash('error', 'Invalid category.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $source = new Source($name, $feedUrl, $category, $this->clock->now());
        $source->setEnabled($enabled);

        if ($siteUrl !== '') {
            $source->setSiteUrl($siteUrl);
        }

        if ($language !== '') {
            $source->setLanguage($language);
        }

        $this->sourceRepository->save($source, flush: true);

        $this->controller->addFlash('success', 'Source created.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function renderForm(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->controller->render('source/new.html.twig', [
            'categories' => $categories,
        ]);
    }
}
