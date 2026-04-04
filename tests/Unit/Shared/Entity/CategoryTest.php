<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Entity;

use App\Shared\Entity\Category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Category::class)]
final class CategoryTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $category = new Category('Technology', 'technology', 10, '#3B82F6');

        self::assertNull($category->getId());
        self::assertSame('Technology', $category->getName());
        self::assertSame('technology', $category->getSlug());
        self::assertSame(10, $category->getWeight());
        self::assertSame('#3B82F6', $category->getColor());
    }

    public function testSetWeight(): void
    {
        $category = new Category('Tech', 'tech', 10, '#000000');
        $category->setWeight(20);

        self::assertSame(20, $category->getWeight());
    }

    public function testSetColor(): void
    {
        $category = new Category('Tech', 'tech', 10, '#000000');
        $category->setColor('#FF0000');

        self::assertSame('#FF0000', $category->getColor());
    }
}
