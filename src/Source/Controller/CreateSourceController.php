<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
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
        $enabled = $request->request->getBoolean('enabled');

        if ($name === '' || $feedUrl === '') {
            $this->controller->addFlash('error', 'Name and Feed URL are required.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $category = $this->entityManager->find(Category::class, $categoryId);
        if ($category === null) {
            $this->controller->addFlash('error', 'Invalid category.');

            return new RedirectResponse($this->urlGenerator->generate('app_sources_new'));
        }

        $source = new Source($name, $feedUrl, $category, $this->clock->now());
        $source->setEnabled($enabled);

        if ($siteUrl !== '') {
            $source->setSiteUrl($siteUrl);
        }

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->controller->addFlash('success', 'Source created.');

        return new RedirectResponse($this->urlGenerator->generate('app_sources'));
    }

    private function renderForm(): Response
    {
        /** @var list<Category> $categories */
        $categories = $this->entityManager->getRepository(Category::class)->findAll();

        return $this->controller->render('source/new.html.twig', [
            'categories' => $categories,
        ]);
    }
}
