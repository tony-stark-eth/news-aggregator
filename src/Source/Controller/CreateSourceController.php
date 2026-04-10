<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Shared\Entity\Category;
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
        $categoryId = (int) $request->request->get('category_id');
        $enabled = $request->request->getBoolean('enabled');
        $fullTextEnabled = $request->request->getBoolean('full_text_enabled');

        if ($name === '' || $feedUrl === '') {
            $this->controller->addFlash('error', 'Name and Feed URL are required.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $category = $this->categoryRepository->findById($categoryId);
        if (! $category instanceof Category) {
            $this->controller->addFlash('error', 'Invalid category.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $source = new Source($name, $feedUrl, $category, $this->clock->now());
        $source->setEnabled($enabled);
        $source->setFullTextEnabled($fullTextEnabled);

        $this->applyOptionalFields($request, $source);

        $this->sourceRepository->save($source, flush: true);

        $this->controller->addFlash('success', 'Source created.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function applyOptionalFields(Request $request, Source $source): void
    {
        $siteUrl = trim((string) $request->request->get('site_url'));
        $language = trim((string) $request->request->get('language'));

        $source->setSiteUrl($siteUrl !== '' ? $siteUrl : null);
        $source->setLanguage($language !== '' ? $language : null);
        $source->setFetchIntervalMinutes(
            $this->parseFetchInterval(trim((string) $request->request->get('fetch_interval_minutes'))),
        );
        $source->setReliabilityWeight(
            $this->parseReliabilityWeight(trim((string) $request->request->get('reliability_weight'))),
        );
    }

    private function parseFetchInterval(string $input): ?int
    {
        if ($input === '') {
            return null;
        }

        $value = (int) $input;

        return ($value >= 5 && $value <= 1440) ? $value : null;
    }

    private function parseReliabilityWeight(string $input): ?float
    {
        if ($input === '') {
            return null;
        }

        $value = (float) $input;

        return ($value >= 0.0 && $value <= 1.0) ? $value : null;
    }

    private function renderForm(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->controller->render('source/new.html.twig', [
            'categories' => $categories,
        ]);
    }
}
