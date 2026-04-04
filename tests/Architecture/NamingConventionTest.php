<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class NamingConventionTest
{
    public function testExceptionsImplementThrowable(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::classname('/.*Exception$/', true))
            ->excluding(Selector::classname('/.*Interface$/', true))
            ->should()
            ->implement()
            ->classes(Selector::classname(\Throwable::class));
    }
}
