<?php

declare(strict_types=1);

namespace App\Tests\Unit\Article\Mercure;

use App\Article\Entity\Article;
use App\Article\Mercure\MercurePublisherService;
use App\Article\ValueObject\EnrichmentStatus;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[CoversClass(MercurePublisherService::class)]
#[UsesClass(EnrichmentStatus::class)]
#[UsesClass(EnrichmentMethod::class)]
final class MercurePublisherServiceTest extends TestCase
{
    public function testPublishArticleCreatedSendsUpdateWithAllPayloadFields(): void
    {
        $article = $this->createFullArticle(1);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame('created', $data['type']);
                self::assertSame(1, $data['articleId']);
                self::assertSame('Test Article', $data['title']);
                self::assertSame('A test summary', $data['summary']);
                self::assertSame('Tech', $data['category']);
                self::assertSame('#3B82F6', $data['categoryColor']);
                self::assertSame(0.85, $data['score']);
                self::assertSame(['php', 'symfony'], $data['keywords']);
                self::assertSame([
                    'de' => [
                        'title' => 'Testartikel',
                        'summary' => 'Testzusammenfassung',
                    ],
                ], $data['translations']);

                return true;
            }));

        $service = new MercurePublisherService($hub, new NullLogger());
        $service->publishArticleCreated($article);
    }

    public function testPublishEnrichmentCompleteSendsUpdateWithAllPayloadFields(): void
    {
        $article = $this->createFullArticle(2);
        $article->setEnrichmentStatus(EnrichmentStatus::Pending);
        $article->setEnrichmentStatus(EnrichmentStatus::Complete);
        $article->setEnrichmentMethod(EnrichmentMethod::Ai);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $data = self::decodePayload($update);
                self::assertSame('enriched', $data['type']);
                self::assertSame(2, $data['articleId']);
                self::assertSame('Test Article', $data['title']);
                self::assertSame('A test summary', $data['summary']);
                self::assertSame('Tech', $data['category']);
                self::assertSame('#3B82F6', $data['categoryColor']);
                self::assertSame('complete', $data['enrichmentStatus']);
                self::assertSame('ai', $data['enrichmentMethod']);
                self::assertSame(0.85, $data['score']);
                self::assertSame(['php', 'symfony'], $data['keywords']);
                self::assertSame([
                    'de' => [
                        'title' => 'Testartikel',
                        'summary' => 'Testzusammenfassung',
                    ],
                ], $data['translations']);

                return true;
            }));

        $service = new MercurePublisherService($hub, new NullLogger());
        $service->publishEnrichmentComplete($article);
    }

    public function testPublishArticleCreatedSkipsWhenIdIsNull(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('No ID', 'https://example.com/no-id', $source, new \DateTimeImmutable());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $service = new MercurePublisherService($hub, new NullLogger());
        $service->publishArticleCreated($article);
    }

    public function testPublishEnrichmentCompleteSkipsWhenIdIsNull(): void
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('No ID', 'https://example.com/no-id', $source, new \DateTimeImmutable());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $service = new MercurePublisherService($hub, new NullLogger());
        $service->publishEnrichmentComplete($article);
    }

    public function testPublishCatchesExceptionAndLogs(): void
    {
        $article = $this->createArticle(3);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Mercure publish failed'),
                self::callback(static function (array $ctx): bool {
                    return $ctx['id'] === 3
                        && $ctx['error'] === 'Connection refused'
                        && $ctx['topic'] === '/articles';
                }),
            );

        $service = new MercurePublisherService($hub, $logger);
        // Should not throw
        $service->publishArticleCreated($article);
    }

    public function testPublishEnrichmentCompleteUsesCorrectTopic(): void
    {
        $article = $this->createArticle(5);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::anything(),
                self::callback(static fn (array $ctx): bool => $ctx['topic'] === '/articles/5/enriched'),
            );

        $service = new MercurePublisherService($hub, $logger);
        $service->publishEnrichmentComplete($article);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePayload(Update $update): array
    {
        $decoded = json_decode($update->getData(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function createArticle(int $id): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Test Article', 'https://example.com/' . $id, $source, new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, $id);

        return $article;
    }

    /**
     * Creates an article with all payload-relevant fields populated,
     * so ArrayItem mutations on buildArticlePayload are killed.
     */
    private function createFullArticle(int $id): Article
    {
        $category = new Category('Tech', 'tech', 10, '#3B82F6');
        $source = new Source('Test', 'https://example.com/feed', $category, new \DateTimeImmutable());
        $article = new Article('Test Article', 'https://example.com/' . $id, $source, new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, $id);

        $article->setCategory($category);
        $article->setSummary('A test summary');
        $article->setScore(0.85);
        $article->setKeywords(['php', 'symfony']);
        $article->setTranslations([
            'de' => [
                'title' => 'Testartikel',
                'summary' => 'Testzusammenfassung',
            ],
        ]);

        return $article;
    }
}
