<?php

declare(strict_types=1);

namespace App\Tests\Integration\Source;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\Service\SourceDeletionService;
use App\Source\Service\SourceDeletionServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(SourceDeletionService::class)]
final class SourceDeletionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private SourceDeletionServiceInterface $deletionService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->deletionService = self::getContainer()->get(SourceDeletionServiceInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testDeleteRemovesSourceAndRelatedArticles(): void
    {
        $category = new Category('Delete Cascade', 'delete-cascade', 1, '#111111');
        $this->em->persist($category);

        $source = new Source(
            'Cascade Source',
            'https://cascade-delete-' . uniqid() . '.example.com/feed.xml',
            $category,
            new \DateTimeImmutable(),
        );
        $this->em->persist($source);

        $article = new Article(
            'Cascade article',
            'https://cascade-delete-' . uniqid() . '.example.com/article',
            $source,
            new \DateTimeImmutable(),
        );
        $this->em->persist($article);
        $this->em->flush();

        $sourceId = $source->getId();
        $articleId = $article->getId();
        self::assertNotNull($sourceId);
        self::assertNotNull($articleId);

        $this->deletionService->delete($source);
        $this->em->clear();

        self::assertNull($this->em->find(Source::class, $sourceId));
        self::assertNull($this->em->find(Article::class, $articleId));
    }

    public function testDeleteManyRemovesSelectedSources(): void
    {
        $category = new Category('Bulk Delete', 'bulk-delete', 1, '#222222');
        $this->em->persist($category);

        $sourceA = new Source(
            'Bulk A',
            'https://bulk-delete-a-' . uniqid() . '.example.com/feed.xml',
            $category,
            new \DateTimeImmutable(),
        );
        $sourceB = new Source(
            'Bulk B',
            'https://bulk-delete-b-' . uniqid() . '.example.com/feed.xml',
            $category,
            new \DateTimeImmutable(),
        );
        $this->em->persist($sourceA);
        $this->em->persist($sourceB);
        $this->em->flush();

        $deletedCount = $this->deletionService->deleteMany([
            (int) $sourceA->getId(),
            (int) $sourceB->getId(),
        ]);
        $this->em->clear();

        self::assertSame(2, $deletedCount);
        self::assertNull($this->em->find(Source::class, $sourceA->getId()));
        self::assertNull($this->em->find(Source::class, $sourceB->getId()));
    }
}
