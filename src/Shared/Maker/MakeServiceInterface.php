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

final class MakeServiceInterface extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:service-interface';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new service interface and implementation';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The context/name of the service (e.g. <fg=yellow>Enrichment/Categorization</>)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        [$context, $name] = NameParser::parse($input->getArgument('name'));

        $namespace = sprintf('App\\%s\\Service', $context);
        $interfacePath = sprintf('src/%s/Service/%sServiceInterface.php', $context, $name);
        $implementationPath = sprintf('src/%s/Service/%sService.php', $context, $name);

        $generator->dumpFile($interfacePath, $this->buildInterface($namespace, $name));
        $generator->dumpFile($implementationPath, $this->buildImplementation($namespace, $name));
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text([
            sprintf('Next: open <info>%s</info> and add method signatures.', $interfacePath),
            sprintf('Then implement them in <info>%s</info>.', $implementationPath),
        ]);
    }

    private function buildInterface(string $namespace, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            interface {$name}ServiceInterface
            {
            }

            PHP;
    }

    private function buildImplementation(string $namespace, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            final readonly class {$name}Service implements {$name}ServiceInterface
            {
                public function __construct()
                {
                }
            }

            PHP;
    }
}
