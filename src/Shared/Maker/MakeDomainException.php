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

final class MakeDomainException extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:domain-exception';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new domain exception class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The context/name of the exception (e.g. <fg=yellow>Source/FeedFetch</>)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        [$context, $name] = NameParser::parse($input->getArgument('name'));

        $namespace = sprintf('App\\%s\\Exception', $context);
        $targetPath = sprintf('src/%s/Exception/%sException.php', $context, $name);

        $generator->dumpFile($targetPath, $this->buildContent($namespace, $name));
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text(sprintf('Next: open <info>%s</info> and add named constructors for specific error cases.', $targetPath));
    }

    private function buildContent(string $namespace, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            final class {$name}Exception extends \RuntimeException
            {
                public static function because(string \$reason, ?\Throwable \$previous = null): self
                {
                    return new self(\$reason, 0, \$previous);
                }
            }

            PHP;
    }
}
