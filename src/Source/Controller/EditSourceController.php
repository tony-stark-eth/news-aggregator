<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Exception\InvalidFeedUrlException;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\ValueObject\FeedUrl;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EditSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/sources/{id}/edit', name: 'app_sources_edit', methods: ['GET', 'POST'])]
    public function __invoke(int $id): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $source = $this->sourceRepository->findById($id);
        if (! $source instanceof Source) {
            $this->controller->addFlash('error', 'Source not found.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources'));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            return $this->handlePost($request, $source, $id);
        }

        return $this->renderForm($source);
    }

    private function handlePost(
        Request $request,
        Source $source,
        int $id,
    ): RedirectResponse {
        if (! $this->controller->isCsrfTokenValid('edit_source', (string) $request->request->get('_token'))) {
            $this->controller->addFlash('error', 'Invalid CSRF token.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_edit', [
                'id' => $id,
            ]));
        }

        $name = trim((string) $request->request->get('name'));
        $feedUrl = trim((string) $request->request->get('feed_url'));
        $siteUrl = trim((string) $request->request->get('site_url'));
        $categoryId = (int) $request->request->get('category_id');
        $language = trim((string) $request->request->get('language'));
        $fetchInterval = trim((string) $request->request->get('fetch_interval_minutes'));
        $enabled = $request->request->getBoolean('enabled');

        if ($name === '' || $feedUrl === '') {
            $this->controller->addFlash('error', 'Name and Feed URL are required.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_edit', [
                'id' => $id,
            ]));
        }

        $category = $this->categoryRepository->findById($categoryId);
        if (! $category instanceof Category) {
            $this->controller->addFlash('error', 'Invalid category.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_edit', [
                'id' => $id,
            ]));
        }

        try {
            new FeedUrl($feedUrl);
        } catch (InvalidFeedUrlException) {
            $this->controller->addFlash('error', 'Invalid feed URL format.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_edit', [
                'id' => $id,
            ]));
        }

        $existing = $this->sourceRepository->findByFeedUrl($feedUrl);
        if ($existing instanceof Source && $existing->getId() !== $source->getId()) {
            $this->controller->addFlash('error', 'A source with this feed URL already exists.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_edit', [
                'id' => $id,
            ]));
        }

        $source->setName($name);
        $source->setFeedUrl($feedUrl);
        $source->setCategory($category);
        $source->setSiteUrl($siteUrl !== '' ? $siteUrl : null);
        $source->setLanguage($language !== '' ? $language : null);
        $source->setEnabled($enabled);

        $source->setFetchIntervalMinutes($this->parseFetchInterval($fetchInterval));

        $this->sourceRepository->save($source, flush: true);

        $this->controller->addFlash('success', 'Source updated.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function parseFetchInterval(string $input): ?int
    {
        if ($input === '') {
            return null;
        }

        $value = (int) $input;

        return ($value >= 5 && $value <= 1440) ? $value : null;
    }

    private function renderForm(Source $source): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->controller->render('source/edit.html.twig', [
            'source' => $source,
            'categories' => $categories,
        ]);
    }
}
