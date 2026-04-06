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

final class MakeEnum extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:enum';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new backed enum class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The context/name of the enum (e.g. <fg=yellow>Notification/AlertUrgency</>)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        [$context, $name] = NameParser::parse($input->getArgument('name'));

        $namespace = sprintf('App\\%s\\Enum', $context);
        $targetPath = sprintf('src/%s/Enum/%s.php', $context, $name);

        $generator->dumpFile($targetPath, $this->buildContent($namespace, $name));
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text(sprintf('Next: open <info>%s</info> and add your enum cases.', $targetPath));
    }

    private function buildContent(string $namespace, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            enum {$name}: string
            {
                case Example = 'example';
            }

            PHP;
    }
}
