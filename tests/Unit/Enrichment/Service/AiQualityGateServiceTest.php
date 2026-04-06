<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Enrichment\Service\AiQualityGateService;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiQualityGateService::class)]
final class AiQualityGateServiceTest extends TestCase
{
    private AiQualityGateService $gate;

    protected function setUp(): void
    {
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findBySlug')->willReturnCallback(
            static fn (string $slug): ?Category => match ($slug) {
                'politics', 'business', 'tech', 'science', 'sports' => new Category($slug, $slug, 10, '#000'),
                default => null,
            },
        );

        $this->gate = new AiQualityGateService($categoryRepo);
    }

    public function testValidSummaryPasses(): void
    {
        self::assertTrue($this->gate->validateSummary(
            'The government announced new economic measures to combat inflation.',
            'Economic Policy Update',
        ));
    }

    public function testTooShortSummaryFails(): void
    {
        self::assertFalse($this->gate->validateSummary('Too short.', 'Title'));
    }

    public function testTooLongSummaryFails(): void
    {
        $long = str_repeat('word ', 120);
        self::assertFalse($this->gate->validateSummary($long, 'Title'));
    }

    public function testTitleRepeatFails(): void
    {
        self::assertFalse($this->gate->validateSummary(
            'Breaking News: Major Event Today',
            'Breaking News: Major Event Today',
        ));
    }

    public function testValidCategorizationPasses(): void
    {
        self::assertTrue($this->gate->validateCategorization('tech'));
        self::assertTrue($this->gate->validateCategorization('politics'));
    }

    public function testInvalidCategorizationFails(): void
    {
        self::assertFalse($this->gate->validateCategorization('unknown'));
        self::assertFalse($this->gate->validateCategorization(''));
    }

    public function testSummaryExactly20CharsPassesLowerBound(): void
    {
        // 20 chars exactly should pass length check
        $summary = str_repeat('a', 20);
        self::assertTrue($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly19CharsFailsLowerBound(): void
    {
        $summary = str_repeat('a', 19);
        self::assertFalse($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly500CharsPassesUpperBound(): void
    {
        $summary = str_repeat('a', 500);
        self::assertTrue($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSummaryExactly501CharsFailsUpperBound(): void
    {
        $summary = str_repeat('a', 501);
        self::assertFalse($this->gate->validateSummary($summary, 'Different Title'));
    }

    public function testSimilarButNotIdenticalTitlePasses(): void
    {
        // Just below 90% similarity should pass
        self::assertTrue($this->gate->validateSummary(
            'The quick brown fox jumps over the lazy dog near the park and the lake area.',
            'A completely different title that has nothing to do with foxes',
        ));
    }
}
