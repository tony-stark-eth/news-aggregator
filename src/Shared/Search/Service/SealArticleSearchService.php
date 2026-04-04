<?php

declare(strict_types=1);

namespace App\Shared\Search\Service;

use App\Article\Entity\Article;
use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Search\Condition\Condition;

final readonly class SealArticleSearchService implements ArticleSearchServiceInterface
{
    private const string INDEX = 'articles';

    public function __construct(
        private EngineInterface $loupeEngine,
    ) {
    }

    public function index(Article $article): void
    {
        $id = $article->getId();
        if ($id === null) {
            return;
        }

        $document = [
            'id' => (string) $id,
            'title' => $article->getTitle(),
            'contentText' => $article->getContentText() ?? '',
            'summary' => $article->getSummary() ?? '',
            'sourceName' => $article->getSource()->getName(),
            'categorySlug' => $article->getCategory()?->getSlug() ?? '',
            'score' => $article->getScore() ?? 0.0,
            'fetchedAt' => $article->getFetchedAt()->format(\DateTimeInterface::ATOM),
        ];

        $this->loupeEngine->saveDocument(self::INDEX, $document);
    }

    public function remove(int $articleId): void
    {
        $this->loupeEngine->deleteDocument(self::INDEX, (string) $articleId);
    }

    /**
     * @return list<int>
     */
    public function search(string $query, ?string $categorySlug = null, int $limit = 50): array
    {
        $builder = $this->loupeEngine->createSearchBuilder(self::INDEX)
            ->addFilter(Condition::search($query))
            ->limit($limit);

        if ($categorySlug !== null && $categorySlug !== '') {
            $builder->addFilter(Condition::equal('categorySlug', $categorySlug));
        }

        $ids = [];
        foreach ($builder->getResult() as $document) {
            $rawId = $document['id'];
            if (\is_string($rawId) || \is_int($rawId)) {
                $ids[] = (int) $rawId;
            }
        }

        return $ids;
    }
}
