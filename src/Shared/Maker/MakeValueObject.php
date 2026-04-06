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

final class MakeValueObject extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:value-object';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new value object class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The context/name of the value object (e.g. <fg=yellow>Article/Url</>)')
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        [$context, $name] = NameParser::parse($input->getArgument('name'));

        $namespace = sprintf('App\\%s\\ValueObject', $context);
        $targetPath = sprintf('src/%s/ValueObject/%s.php', $context, $name);

        $generator->dumpFile($targetPath, $this->buildContent($namespace, $name));
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text(sprintf('Next: open <info>%s</info> and customize the validation logic.', $targetPath));
    }

    private function buildContent(string $namespace, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            final readonly class {$name} implements \Stringable
            {
                public string \$value;

                public function __construct(string \$value)
                {
                    \$trimmed = trim(\$value);
                    if (\$trimmed === '') {
                        throw new \InvalidArgumentException('{$name} must not be empty.');
                    }

                    \$this->value = \$trimmed;
                }

                public function __toString(): string
                {
                    return \$this->value;
                }

                public function equals(self \$other): bool
                {
                    return \$this->value === \$other->value;
                }
            }

            PHP;
    }
}
