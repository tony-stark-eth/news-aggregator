<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Twig;

use App\Shared\Twig\ColorContrastExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColorContrastExtension::class)]
final class ColorContrastExtensionTest extends TestCase
{
    private ColorContrastExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ColorContrastExtension();
    }

    public function testGetFiltersReturnsContrastTextColorFilter(): void
    {
        $filters = $this->extension->getFilters();

        self::assertCount(1, $filters);
        self::assertSame('contrast_text_color', $filters[0]->getName());
    }

    #[DataProvider('contrastColorProvider')]
    public function testContrastTextColor(string $hexColor, string $expected): void
    {
        self::assertSame($expected, $this->extension->contrastTextColor($hexColor));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function contrastColorProvider(): iterable
    {
        // Dark backgrounds -> white text
        yield 'black' => ['#000000', '#ffffff'];
        yield 'dark blue (night)' => ['#1a1b26', '#ffffff'];
        yield 'dark green' => ['#006400', '#ffffff'];
        yield 'navy' => ['#000080', '#ffffff'];

        // Light backgrounds -> black text
        yield 'white' => ['#ffffff', '#000000'];
        yield 'yellow' => ['#ffff00', '#000000'];
        yield 'light pink' => ['#ffb6c1', '#000000'];
        yield 'light blue' => ['#e0f2fe', '#000000'];

        // Edge cases
        yield 'with hash prefix' => ['#333333', '#ffffff'];
        yield 'without hash prefix' => ['333333', '#ffffff'];

        // Boundary: mid-gray (luminance ~0.216 > 0.179 -> black text)
        yield 'mid-gray' => ['#808080', '#000000'];

        // Invalid hex -> defaults to white
        yield 'invalid short hex' => ['#fff', '#ffffff'];
        yield 'invalid string' => ['notahex', '#ffffff'];
    }

    public function testContrastTextColorWithHashStripping(): void
    {
        // Ensure both '#AABBCC' and 'AABBCC' produce the same result
        self::assertSame(
            $this->extension->contrastTextColor('#AABBCC'),
            $this->extension->contrastTextColor('AABBCC'),
        );
    }
}
