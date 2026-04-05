<?php

declare(strict_types=1);

namespace App\Source\Command;

use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:fetch-sources',
    description: 'Dispatch fetch messages for all enabled sources',
)]
final class FetchSourcesCommand extends Command
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sources = $this->sourceRepository->findEnabled();

        $dispatched = 0;
        foreach ($sources as $source) {
            $id = $source->getId();
            if ($id === null) {
                continue;
            }

            $this->messageBus->dispatch(new FetchSourceMessage($id));
            $io->info(sprintf('Dispatched fetch for: %s', $source->getName()));
            $dispatched++;
        }

        $io->success(sprintf('Dispatched %d fetch messages.', $dispatched));

        return Command::SUCCESS;
    }
}
