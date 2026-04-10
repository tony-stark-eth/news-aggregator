<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enrichment\Service;

use App\Article\Entity\Article;
use App\Article\ValueObject\Url;
use App\Enrichment\Service\ArticleTranslationService;
use App\Enrichment\Service\BatchTranslationServiceInterface;
use App\Enrichment\ValueObject\BatchTranslationResult;
use App\Shared\Service\SettingsServiceInterface;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArticleTranslationService::class)]
#[UsesClass(BatchTranslationResult::class)]
#[UsesClass(Url::class)]
final class ArticleTranslationServiceTest extends TestCase
{
    public function testAppliesTranslationsForMultipleLanguages(): void
    {
        $batch = $this->createMock(BatchTranslationServiceInterface::class);
        $batch->expects(self::exactly(2))
            ->method('translateBatch')
            ->willReturnCallback(
                static function (string $title, ?string $summary, array $kw, string $from, string $to): BatchTranslationResult {
                    $translatedSummary = $summary !== null ? $to . ':' . $summary : null;
                    $translatedKw = [];
                    foreach ($kw as $k) {
                        \assert(\is_string($k));
                        $translatedKw[] = $to . ':' . $k;
                    }

                    return new BatchTranslationResult(
                        $to . ':' . $title,
                        $translatedSummary,
                        $translatedKw,
                        true,
                    );
                },
            );

        $settings = $this->createSettingsService('en,de,fr');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('Headline', 'Summary text', ['KW1']);
        $source = $this->createSource('en');

        $service->applyTranslations($article, $source);

        $translations = $article->getTranslations();
        self::assertNotNull($translations);
        self::assertArrayHasKey('en', $translations);
        self::assertArrayHasKey('de', $translations);
        self::assertArrayHasKey('fr', $translations);

        self::assertSame('Headline', $translations['en']['title']);
        self::assertSame('de:Headline', $translations['de']['title']);
        self::assertSame('fr:Headline', $translations['fr']['title']);
    }

    public function testSkipsSourceLanguageTranslation(): void
    {
        $batch = $this->createMock(BatchTranslationServiceInterface::class);
        $batch->expects(self::once())->method('translateBatch')
            ->willReturn(new BatchTranslationResult('DE Title', 'DE Summary', [], true));

        $settings = $this->createSettingsService('en,de');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('Title', 'Summary', []);
        $source = $this->createSource('en');

        $service->applyTranslations($article, $source);

        $translations = $article->getTranslations();
        self::assertNotNull($translations);
        self::assertArrayHasKey('en', $translations);
        self::assertArrayHasKey('de', $translations);
    }

    public function testSetsPrimaryLanguageTitleWhenDifferentFromSource(): void
    {
        $batch = $this->createStub(BatchTranslationServiceInterface::class);
        $batch->method('translateBatch')->willReturn(
            new BatchTranslationResult('English Title', 'English Summary', [], true),
        );

        $settings = $this->createSettingsService('en');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('German Title', 'German Summary', []);
        $source = $this->createSource('de');

        $service->applyTranslations($article, $source);

        // Primary language is 'en', source is 'de', so title should be updated
        self::assertSame('English Title', $article->getTitle());
        self::assertSame('English Summary', $article->getSummary());
    }

    public function testPreservesTitleWhenPrimaryMatchesSource(): void
    {
        $batch = $this->createMock(BatchTranslationServiceInterface::class);
        $batch->expects(self::never())->method('translateBatch');

        $settings = $this->createSettingsService('en');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('English Title', 'English Summary', []);
        $source = $this->createSource('en');

        $service->applyTranslations($article, $source);

        self::assertSame('English Title', $article->getTitle());
    }

    public function testSetsOriginalTitleAndSummary(): void
    {
        $batch = $this->createStub(BatchTranslationServiceInterface::class);
        $batch->method('translateBatch')->willReturn(
            new BatchTranslationResult('Translated', null, [], true),
        );

        $settings = $this->createSettingsService('en,de');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('Original', 'Original Summary', []);
        $source = $this->createSource('en');

        $service->applyTranslations($article, $source);

        self::assertSame('Original', $article->getTitleOriginal());
        self::assertSame('Original Summary', $article->getSummaryOriginal());
    }

    public function testDefaultsSourceLanguageToEnglish(): void
    {
        $batch = $this->createMock(BatchTranslationServiceInterface::class);
        $batch->expects(self::never())->method('translateBatch');

        $settings = $this->createSettingsService('en');
        $service = new ArticleTranslationService($batch, $settings);

        $article = $this->createArticle('Title', null, []);
        $source = $this->createSource(null);

        $service->applyTranslations($article, $source);

        $translations = $article->getTranslations();
        self::assertNotNull($translations);
        self::assertArrayHasKey('en', $translations);
    }

    /**
     * @param list<string> $keywords
     */
    private function createArticle(string $title, ?string $summary, array $keywords): Article
    {
        $source = $this->createStub(Source::class);
        $article = new Article($title, 'https://example.com/test', $source, new \DateTimeImmutable());
        $article->setSummary($summary);
        $article->setKeywords($keywords);

        return $article;
    }

    private function createSource(?string $language): Source
    {
        $source = $this->createStub(Source::class);
        $source->method('getLanguage')->willReturn($language);

        return $source;
    }

    private function createSettingsService(string $displayLanguages): SettingsServiceInterface
    {
        $settings = $this->createStub(SettingsServiceInterface::class);
        $settings->method('getDisplayLanguages')->willReturn($displayLanguages);

        return $settings;
    }
}
