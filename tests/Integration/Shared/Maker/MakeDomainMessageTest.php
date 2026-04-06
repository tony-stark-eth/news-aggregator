<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Maker;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;

#[CoversNothing]
final class MakeDomainMessageTest extends AbstractMakerTestCase
{
    private const string COMMAND = 'make:domain-message';

    public function testGeneratesMessageAndHandler(): void
    {
        $this->trackGeneratedFiles([
            'src/MakerTest/Message/SendAlertMessage.php',
            'src/MakerTest/MessageHandler/SendAlertHandler.php',
        ]);

        $tester = $this->runMakerCommand(self::COMMAND, 'MakerTest/SendAlert');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify message
        $messageContent = $this->assertFileWasGenerated('src/MakerTest/Message/SendAlertMessage.php');
        $this->assertPhpFileConventions($messageContent, 'App\MakerTest\Message', 'final readonly class SendAlertMessage');
        self::assertStringContainsString('public int $id', $messageContent);
        $this->assertValidPhpSyntax('src/MakerTest/Message/SendAlertMessage.php');

        // Verify handler
        $handlerContent = $this->assertFileWasGenerated('src/MakerTest/MessageHandler/SendAlertHandler.php');
        $this->assertPhpFileConventions($handlerContent, 'App\MakerTest\MessageHandler', 'final readonly class SendAlertHandler');
        self::assertStringContainsString('#[AsMessageHandler]', $handlerContent);
        self::assertStringContainsString('use App\MakerTest\Message\SendAlertMessage', $handlerContent);
        self::assertStringContainsString('public function __invoke(SendAlertMessage $message): void', $handlerContent);
        $this->assertValidPhpSyntax('src/MakerTest/MessageHandler/SendAlertHandler.php');
    }
}
