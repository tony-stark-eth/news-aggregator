<?php

declare(strict_types=1);

namespace App\Tests\Integration\Source\Entity;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\Source\ValueObject\SourceHealth;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Source::class)]
final class SourceRepositoryTest extends KernelTestCase
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
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $this->em->persist($category);

        $source = new Source(
            'Test Source',
            'https://example.com/feed.xml',
            $category,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
        $this->em->persist($source);
        $this->em->flush();

        $id = $source->getId();
        self::assertNotNull($id);

        $this->em->clear();

        $found = $this->em->getRepository(Source::class)->find($id);
        self::assertInstanceOf(Source::class, $found);
        self::assertSame('Test Source', $found->getName());
        self::assertSame('https://example.com/feed.xml', $found->getFeedUrl());
        self::assertSame(SourceHealth::Healthy, $found->getHealthStatus());
        self::assertSame('Tech', $found->getCategory()->getName());
    }

    public function testFindByFeedUrl(): void
    {
        $category = new Category('Biz', 'biz', 5, '#F59E0B');
        $this->em->persist($category);

        $source = new Source(
            'URL Test',
            'https://example.com/unique-feed.xml',
            $category,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
        $this->em->persist($source);
        $this->em->flush();
        $this->em->clear();

        $found = $this->em->getRepository(Source::class)->findOneBy([
            'feedUrl' => 'https://example.com/unique-feed.xml',
        ]);
        self::assertInstanceOf(Source::class, $found);
        self::assertSame('URL Test', $found->getName());
    }

    public function testHealthStatusPersistedAfterFailure(): void
    {
        $category = new Category('Sci', 'sci', 7, '#10B981');
        $this->em->persist($category);

        $source = new Source(
            'Failing Source',
            'https://example.com/failing.xml',
            $category,
            new \DateTimeImmutable('2026-04-04 12:00:00'),
        );
        $source->recordFailure('timeout', new \DateTimeImmutable('2026-04-04 12:00:00'));
        $source->recordFailure('timeout', new \DateTimeImmutable('2026-04-04 13:00:00'));
        $source->recordFailure('timeout', new \DateTimeImmutable('2026-04-04 14:00:00'));
        $this->em->persist($source);
        $this->em->flush();

        $id = $source->getId();
        $this->em->clear();

        $found = $this->em->getRepository(Source::class)->find($id);
        self::assertInstanceOf(Source::class, $found);
        self::assertSame(SourceHealth::Failing, $found->getHealthStatus());
        self::assertSame(3, $found->getErrorCount());
    }
}
