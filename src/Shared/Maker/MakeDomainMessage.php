<?php

declare(strict_types=1);

namespace App\Shared\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MakeDomainMessage extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:domain-message';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new message and handler class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The context/name of the message (e.g. <fg=yellow>Notification/SendNotification</>)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        [$context, $name] = NameParser::parse($input->getArgument('name'));

        $messagePath = sprintf('src/%s/Message/%sMessage.php', $context, $name);
        $handlerPath = sprintf('src/%s/MessageHandler/%sHandler.php', $context, $name);

        $generator->dumpFile($messagePath, $this->buildMessage($context, $name));
        $generator->dumpFile($handlerPath, $this->buildHandler($context, $name));
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text([
            sprintf('Next: open <info>%s</info> and add message properties.', $messagePath),
            sprintf('Then implement the handler in <info>%s</info>.', $handlerPath),
        ]);
    }

    private function buildMessage(string $context, string $name): string
    {
        $namespace = sprintf('App\\%s\\Message', $context);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            final readonly class {$name}Message
            {
                public function __construct(
                    public int \$id,
                ) {
                }
            }

            PHP;
    }

    private function buildHandler(string $context, string $name): string
    {
        $namespace = sprintf('App\\%s\\MessageHandler', $context);
        $messageNamespace = sprintf('App\\%s\\Message\\%sMessage', $context, $name);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use {$messageNamespace};
            use Symfony\Component\Messenger\Attribute\AsMessageHandler;

            #[AsMessageHandler]
            final readonly class {$name}Handler
            {
                public function __construct()
                {
                }

                public function __invoke({$name}Message \$message): void
                {
                }
            }

            PHP;
    }
}
