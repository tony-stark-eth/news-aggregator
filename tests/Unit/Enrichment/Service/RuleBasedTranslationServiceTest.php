<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\RuleBasedTranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleBasedTranslationService::class)]
final class RuleBasedTranslationServiceTest extends TestCase
{
    private RuleBasedTranslationService $service;

    protected function setUp(): void
    {
        $this->service = new RuleBasedTranslationService();
    }

    public function testReturnsOriginalText(): void
    {
        $text = 'Bundesregierung beschließt neue Maßnahmen';

        $result = $this->service->translate($text, 'de', 'en');

        self::assertSame($text, $result);
    }

    public function testReturnsOriginalForSameLanguage(): void
    {
        $text = 'Some English text';

        $result = $this->service->translate($text, 'en', 'en');

        self::assertSame($text, $result);
    }

    public function testHandlesEmptyText(): void
    {
        $result = $this->service->translate('', 'de', 'en');

        self::assertSame('', $result);
    }
}
