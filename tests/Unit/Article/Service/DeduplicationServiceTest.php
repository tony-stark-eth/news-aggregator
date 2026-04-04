<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Service;

use App\Article\Entity\Article;
use App\Article\Service\DeduplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeduplicationService::class)]
final class DeduplicationServiceTest extends TestCase
{
    public function testIsDuplicateByUrl(): void
    {
        $article = $this->createStub(Article::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($article);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new DeduplicationService($em);

        self::assertTrue($service->isDuplicate('https://example.com/existing', 'Any Title', null));
    }

    public function testIsNotDuplicateForNewUrl(): void
    {
        $service = new DeduplicationService($this->buildEmWithTitles([]));

        self::assertFalse($service->isDuplicate('https://example.com/new', 'Unique Title', null));
    }

    public function testIsDuplicateByFingerprint(): void
    {
        $callCount = 0;
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use (&$callCount): mixed {
                $callCount++;
                if ($callCount === 1) {
                    return null; // URL not found
                }

                return isset($criteria['fingerprint']) ? new \stdClass() : null;
            });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new DeduplicationService($em);

        self::assertTrue($service->isDuplicate('https://example.com/new', 'Title', 'abc123'));
    }

    public function testIsDuplicateBySimilarTitle(): void
    {
        $service = new DeduplicationService(
            $this->buildEmWithTitles([[
                'title' => 'Breaking: Major Event Happens Today',
            ]]),
        );

        self::assertTrue($service->isDuplicate(
            'https://example.com/new',
            'Breaking: Major Event Happens Today!',
            null,
        ));
    }

    /**
     * @param list<array{title: string}> $titles
     */
    private function buildEmWithTitles(array $titles): EntityManagerInterface
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($titles);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }
}
