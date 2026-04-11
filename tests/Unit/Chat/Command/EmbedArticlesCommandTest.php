<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Command;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Chat\Command\EmbedArticlesCommand;
use App\Chat\Message\GenerateEmbeddingMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(EmbedArticlesCommand::class)]
final class EmbedArticlesCommandTest extends TestCase
{
    public function testDispatchesEmbeddingMessagesForArticlesWithoutEmbeddings(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->expects(self::once())->method('findIdsWithoutEmbeddings')
            ->with(200)
            ->willReturn([1, 2, 3]);

        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(3))->method('dispatch')
            ->with(self::callback(static function (GenerateEmbeddingMessage $msg) use (&$dispatched): bool {
                $dispatched[] = $msg->articleId;

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $command = new EmbedArticlesCommand($articleRepo, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Dispatched 3 embedding jobs', $tester->getDisplay());
        self::assertSame([1, 2, 3], $dispatched);
    }

    public function testDryRunShowsCountWithoutDispatching(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->expects(self::once())->method('findIdsWithoutEmbeddings')
            ->with(200)
            ->willReturn([1, 2]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new EmbedArticlesCommand($articleRepo, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([
            '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Found 2 articles without embeddings (dry run)', $tester->getDisplay());
    }

    public function testNoArticlesNeedEmbeddings(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->expects(self::once())->method('findIdsWithoutEmbeddings')
            ->willReturn([]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new EmbedArticlesCommand($articleRepo, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No articles need embeddings', $tester->getDisplay());
    }

    public function testCustomLimitIsPassedToRepository(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->expects(self::once())->method('findIdsWithoutEmbeddings')
            ->with(50)
            ->willReturn([]);

        $messageBus = $this->createStub(MessageBusInterface::class);

        $command = new EmbedArticlesCommand($articleRepo, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([
            '--limit' => '50',
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testZeroLimitUsesHighDefault(): void
    {
        $articleRepo = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepo->expects(self::once())->method('findIdsWithoutEmbeddings')
            ->with(10_000)
            ->willReturn([]);

        $messageBus = $this->createStub(MessageBusInterface::class);

        $command = new EmbedArticlesCommand($articleRepo, $messageBus);
        $tester = new CommandTester($command);
        $tester->execute([
            '--limit' => '0',
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
