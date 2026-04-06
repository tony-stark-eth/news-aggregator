<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiTextCleanupService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiTextCleanupService::class)]
final class AiTextCleanupServiceTest extends TestCase
{
    private AiTextCleanupService $service;

    protected function setUp(): void
    {
        $this->service = new AiTextCleanupService();
    }

    #[DataProvider('splitProvider')]
    public function testSplitsCamelCaseJoins(string $input, string $expected): void
    {
        self::assertSame($expected, $this->service->clean($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function splitProvider(): iterable
    {
        yield 'InformationSecurity' => ['InformationSecurity', 'Information Security'];
        yield 'BayernMunich' => ['BayernMunich', 'Bayern Munich'];
        yield 'NewYorkCity' => ['NewYorkCity', 'New York City'];
        yield 'mid-sentence join' => [
            'The report coversInformationSecurity threats in Europe.',
            'The report covers Information Security threats in Europe.',
        ];
        yield 'multiple joins' => [
            'BayernMunich beatRealMadrid inChampionsLeague',
            'Bayern Munich beat Real Madrid in Champions League',
        ];
    }

    #[DataProvider('preserveProvider')]
    public function testPreservesBrandNamesAndAbbreviations(string $input): void
    {
        self::assertSame($input, $this->service->clean($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function preserveProvider(): iterable
    {
        yield 'iPhone' => ['iPhone'];
        yield 'eBay' => ['eBay'];
        yield "McDonald's" => ["McDonald's"];
        yield 'pH' => ['pH'];
        yield 'HTTPServer' => ['HTTPServer'];
        yield 'iOS' => ['iOS'];
        yield 'eCommerce' => ['eCommerce'];
    }

    public function testReturnsEmptyStringUnchanged(): void
    {
        self::assertSame('', $this->service->clean(''));
    }

    public function testPreservesAlreadyCorrectText(): void
    {
        $text = 'This is a normal sentence with proper spacing.';

        self::assertSame($text, $this->service->clean($text));
    }
}
