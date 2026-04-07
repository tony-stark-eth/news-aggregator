<?php

declare(strict_types=1);

namespace App\Article\Entity;

use App\Article\ValueObject\EnrichmentStatus;
use App\Article\ValueObject\FullTextStatus;
use App\Article\ValueObject\Url;
use App\Shared\Entity\Category;
use App\Shared\ValueObject\EnrichmentMethod;
use App\Source\Entity\Source;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article')]
#[ORM\Index(name: 'idx_article_fingerprint', columns: ['fingerprint'])]
#[ORM\Index(name: 'idx_article_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_article_url', columns: ['url'])]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512)]
    private string $title;

    #[ORM\Column(length: 2048, unique: true)]
    private string $url;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentRaw = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentText = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $score = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Source $source;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(length: 20, nullable: true, enumType: EnrichmentMethod::class)]
    private ?EnrichmentMethod $enrichmentMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $aiModelUsed = null;

    #[ORM\Column(length: 20, nullable: true, enumType: EnrichmentStatus::class)]
    private ?EnrichmentStatus $enrichmentStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $titleOriginal = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summaryOriginal = null;

    /**
     * Translations map: {"de": {"title": "...", "summary": "..."}, "en": {...}, "fr": {...}}
     *
     * @var array<string, array{title: string, summary: string|null}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $translations = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $keywords = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentFullText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentFullHtml = null;

    #[ORM\Column(length: 20, nullable: true, enumType: FullTextStatus::class)]
    private ?FullTextStatus $fullTextStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        string $title,
        string $url,
        Source $source,
        \DateTimeImmutable $fetchedAt,
    ) {
        new Url($url); // validate URL format
        $this->title = $title;
        $this->url = $url;
        $this->source = $source;
        $this->fetchedAt = $fetchedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getContentRaw(): ?string
    {
        return $this->contentRaw;
    }

    public function setContentRaw(?string $contentRaw): void
    {
        $this->contentRaw = $contentRaw;
    }

    public function getContentText(): ?string
    {
        return $this->contentText;
    }

    public function setContentText(?string $contentText): void
    {
        $this->contentText = $contentText;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): void
    {
        $this->score = $score;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function getEnrichmentMethod(): ?EnrichmentMethod
    {
        return $this->enrichmentMethod;
    }

    public function setEnrichmentMethod(?EnrichmentMethod $enrichmentMethod): void
    {
        $this->enrichmentMethod = $enrichmentMethod;
    }

    public function getAiModelUsed(): ?string
    {
        return $this->aiModelUsed;
    }

    public function setAiModelUsed(?string $aiModelUsed): void
    {
        $this->aiModelUsed = $aiModelUsed;
    }

    public function getEnrichmentStatus(): ?EnrichmentStatus
    {
        return $this->enrichmentStatus;
    }

    public function setEnrichmentStatus(?EnrichmentStatus $enrichmentStatus): void
    {
        $this->enrichmentStatus = $enrichmentStatus;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getTitleOriginal(): ?string
    {
        return $this->titleOriginal;
    }

    public function setTitleOriginal(?string $titleOriginal): void
    {
        $this->titleOriginal = $titleOriginal;
    }

    public function getSummaryOriginal(): ?string
    {
        return $this->summaryOriginal;
    }

    public function setSummaryOriginal(?string $summaryOriginal): void
    {
        $this->summaryOriginal = $summaryOriginal;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return list<string>|null
     */
    public function getKeywords(): ?array
    {
        return $this->keywords;
    }

    /**
     * @param list<string>|null $keywords
     */
    public function setKeywords(?array $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * @return array<string, array{title: string, summary: string|null, keywords?: list<string>}>|null
     */
    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    /**
     * @param array<string, array{title: string, summary: string|null, keywords?: list<string>}>|null $translations
     */
    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }

    /**
     * @return array{title: string, summary: string|null}|null
     */
    public function getTranslation(string $lang): ?array
    {
        return $this->translations[$lang] ?? null;
    }

    public function getContentFullText(): ?string
    {
        return $this->contentFullText;
    }

    public function setContentFullText(?string $contentFullText): void
    {
        $this->contentFullText = $contentFullText;
    }

    public function getContentFullHtml(): ?string
    {
        return $this->contentFullHtml;
    }

    public function setContentFullHtml(?string $contentFullHtml): void
    {
        $this->contentFullHtml = $contentFullHtml;
    }

    public function getFullTextStatus(): ?FullTextStatus
    {
        return $this->fullTextStatus;
    }

    public function setFullTextStatus(?FullTextStatus $fullTextStatus): void
    {
        $this->fullTextStatus = $fullTextStatus;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
