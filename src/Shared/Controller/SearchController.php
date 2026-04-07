<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Search\Service\ArticleSearchServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ArticleSearchServiceInterface $searchService,
    ) {
    }

    #[Route('/search', name: 'app_search')]
    public function __invoke(
        Request $request,
        #[MapQueryParameter]
        string $q = '',
        #[MapQueryParameter]
        string $category = '',
    ): Response {
        $query = $q;
        $categorySlug = $category !== '' ? $category : null;
        $results = [];

        if ($query !== '') {
            $ids = $this->searchService->search($query, $categorySlug);

            if ($ids !== []) {
                $results = $this->articleRepository->findByIds($ids);
            }
        }

        $template = $request->headers->has('HX-Request')
            ? 'search/_results.html.twig'
            : 'search/index.html.twig';

        return $this->controller->render($template, [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
