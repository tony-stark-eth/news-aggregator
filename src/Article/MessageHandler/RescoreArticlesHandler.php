<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RescoreArticlesHandler
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScoringServiceInterface $scoringService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(\App\Article\Message\RescoreArticlesMessage $message): void
    {
        $offset = 0;
        $count = 0;

        do {
            /** @var list<Article> $articles */
            $articles = $this->entityManager
                ->getRepository(Article::class)
                ->createQueryBuilder('a')
                ->setFirstResult($offset)
                ->setMaxResults(self::BATCH_SIZE)
                ->getQuery()
                ->getResult();

            foreach ($articles as $article) {
                $article->setScore($this->scoringService->score($article));
                $count++;
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += self::BATCH_SIZE;
        } while (\count($articles) === self::BATCH_SIZE);

        $this->logger->info('Rescored {count} articles', [
            'count' => $count,
        ]);
    }
}
