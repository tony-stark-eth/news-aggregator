<?php

declare(strict_types=1);

namespace App\Shared\AI\Command;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai-smoke-test',
    description: 'Test AI platform connectivity with a simple prompt',
)]
final class AiSmokeTestCommand extends Command
{
    public function __construct(
        private readonly PlatformInterface $platform,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Sending test prompt to OpenRouter...');

        try {
            $messageBag = new MessageBag(Message::ofUser('Respond with exactly one word: hello'));
            $result = $this->platform->invoke('openrouter/auto', $messageBag);
            $text = $result->asText();

            $io->success(sprintf('AI Response: "%s"', $text));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('AI call failed: %s', $e->getMessage()));
            if ($e->getPrevious() instanceof \Throwable) {
                $io->error(sprintf('Caused by: %s', $e->getPrevious()->getMessage()));
            }

            return Command::FAILURE;
        }
    }
}
