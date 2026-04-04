<?php

declare(strict_types=1);

namespace App\Tests\Integration\Article\Entity;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Article::class)]
final class ArticleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testPersistAndFind(): void
    {
        $source = $this->createSource();

        $article = new Article(
            'Test Article',
            'https://example.com/article/1',
            $source,
            new \DateTimeImmutable('2026-04-04 12:30:00'),
        );
        $article->setContentRaw('<p>Hello</p>');
        $article->setContentText('Hello');
        $article->setFingerprint('abc123');
        $this->em->persist($article);
        $this->em->flush();

        $id = $article->getId();
        self::assertNotNull($id);

        $this->em->clear();

        $found = $this->em->getRepository(Article::class)->find($id);
        self::assertInstanceOf(Article::class, $found);
        self::assertSame('Test Article', $found->getTitle());
        self::assertSame('https://example.com/article/1', $found->getUrl());
        self::assertSame('<p>Hello</p>', $found->getContentRaw());
        self::assertSame('Hello', $found->getContentText());
        self::assertSame('abc123', $found->getFingerprint());
    }

    public function testEnrichmentFieldsPersisted(): void
    {
        $source = $this->createSource();

        $article = new Article(
            'Enriched Article',
            'https://example.com/article/2',
            $source,
            new \DateTimeImmutable('2026-04-04 13:00:00'),
        );
        $article->setEnrichmentMethod(EnrichmentMethod::Ai);
        $article->setAiModelUsed('openrouter/free');
        $article->setSummary('A brief summary of the article.');
        $article->setScore(0.85);
        $this->em->persist($article);
        $this->em->flush();

        $id = $article->getId();
        $this->em->clear();

        $found = $this->em->getRepository(Article::class)->find($id);
        self::assertInstanceOf(Article::class, $found);
        self::assertSame(EnrichmentMethod::Ai, $found->getEnrichmentMethod());
        self::assertSame('openrouter/free', $found->getAiModelUsed());
        self::assertSame('A brief summary of the article.', $found->getSummary());
        self::assertSame(0.85, $found->getScore());
    }

    public function testFindByUrl(): void
    {
        $source = $this->createSource();

        $article = new Article(
            'URL Lookup',
            'https://example.com/article/unique',
            $source,
            new \DateTimeImmutable('2026-04-04 14:00:00'),
        );
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $found = $this->em->getRepository(Article::class)->findOneBy([
            'url' => 'https://example.com/article/unique',
        ]);
        self::assertInstanceOf(Article::class, $found);
        self::assertSame('URL Lookup', $found->getTitle());
    }

    private function createSource(): Source
    {
        $category = new Category('Tech', 'tech-art', 10, '#3B82F6');
        $this->em->persist($category);

        $source = new Source(
            'Test Source',
            'https://example.com/art-feed.xml',
            $category,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
        $this->em->persist($source);

        return $source;
    }
}
