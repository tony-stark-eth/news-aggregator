<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\Source\Service\SourceDeletionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceDeletionService::class)]
final class SourceDeletionServiceTest extends TestCase
{
    private SourceRepositoryInterface&MockObject $sourceRepository;

    private SourceDeletionService $service;

    protected function setUp(): void
    {
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->service = new SourceDeletionService($this->sourceRepository);
    }

    public function testDeleteFlushesSingleSource(): void
    {
        $source = $this->createMock(Source::class);

        $this->sourceRepository
            ->expects(self::once())
            ->method('remove')
            ->with($source, true);

        $this->service->delete($source);
    }

    public function testDeleteManyRemovesMatchingSources(): void
    {
        $sourceA = $this->createMock(Source::class);
        $sourceB = $this->createMock(Source::class);

        $this->sourceRepository
            ->expects(self::once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([$sourceA, $sourceB]);

        $this->sourceRepository
            ->expects(self::exactly(2))
            ->method('remove')
            ->with(self::logicalOr($sourceA, $sourceB), false);

        $this->sourceRepository
            ->expects(self::once())
            ->method('flush');

        self::assertSame(2, $this->service->deleteMany([1, 2]));
    }
}
