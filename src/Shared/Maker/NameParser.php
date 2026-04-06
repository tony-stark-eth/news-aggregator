<?php

declare(strict_types=1);

namespace App\Shared\Maker;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

/**
 * Parses "Context/Name" arguments used by all custom makers.
 *
 * @internal
 */
final class NameParser
{
    /**
     * @return array{0: string, 1: string} [context, name]
     */
    public static function parse(mixed $input): array
    {
        if (! \is_string($input)) {
            throw new RuntimeCommandException('The name argument must be a string.');
        }

        $input = trim($input);

        if (! str_contains($input, '/')) {
            throw new RuntimeCommandException(
                sprintf('The name "%s" must contain a context prefix separated by "/", e.g. "Article/Url".', $input),
            );
        }

        $parts = explode('/', $input, 2);
        $context = trim($parts[0]);
        $name = trim($parts[1]);

        if ($context === '' || $name === '') {
            throw new RuntimeCommandException(
                sprintf('The name "%s" has an empty context or name part. Expected format: "Context/Name".', $input),
            );
        }

        return [$context, $name];
    }
}
