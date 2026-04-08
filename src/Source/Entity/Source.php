<?php

declare(strict_types=1);

namespace App\Source\Entity;

use App\Shared\Entity\Category;
use App\Source\ValueObject\FeedUrl;
use App\Source\ValueObject\SourceHealth;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'source')]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 2048, unique: true)]
    private string $feedUrl;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $siteUrl = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastErrorMessage = null;

    #[ORM\Column(length: 20, enumType: SourceHealth::class)]
    private SourceHealth $healthStatus = SourceHealth::Healthy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastFetchedAt = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $fetchIntervalMinutes = null;

    #[ORM\Column]
    private bool $fullTextEnabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $name,
        string $feedUrl,
        Category $category,
        \DateTimeImmutable $createdAt,
    ) {
        new FeedUrl($feedUrl); // validate feed URL format
        $this->name = $name;
        $this->feedUrl = $feedUrl;
        $this->category = $category;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    public function setFeedUrl(string $feedUrl): void
    {
        new FeedUrl($feedUrl);
        $this->feedUrl = $feedUrl;
    }

    public function getSiteUrl(): ?string
    {
        return $this->siteUrl;
    }

    public function setSiteUrl(?string $siteUrl): void
    {
        $this->siteUrl = $siteUrl;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): void
    {
        $this->category = $category;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function getHealthStatus(): SourceHealth
    {
        return $this->healthStatus;
    }

    public function getLastFetchedAt(): ?\DateTimeImmutable
    {
        return $this->lastFetchedAt;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }

    public function getFetchIntervalMinutes(): ?int
    {
        return $this->fetchIntervalMinutes;
    }

    public function setFetchIntervalMinutes(?int $fetchIntervalMinutes): void
    {
        $this->fetchIntervalMinutes = $fetchIntervalMinutes;
    }

    public function isFullTextEnabled(): bool
    {
        return $this->fullTextEnabled;
    }

    public function setFullTextEnabled(bool $fullTextEnabled): void
    {
        $this->fullTextEnabled = $fullTextEnabled;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function recordSuccess(\DateTimeImmutable $fetchedAt): void
    {
        $this->errorCount = 0;
        $this->lastErrorMessage = null;
        $this->healthStatus = SourceHealth::Healthy;
        $this->lastFetchedAt = $fetchedAt;
    }

    public function recordFailure(string $errorMessage): void
    {
        $this->errorCount++;
        $this->lastErrorMessage = $errorMessage;

        $this->healthStatus = match (true) {
            $this->errorCount >= 5 => SourceHealth::Disabled,
            $this->errorCount >= 3 => SourceHealth::Failing,
            $this->errorCount >= 1 => SourceHealth::Degraded,
            default => SourceHealth::Healthy,
        };

        if ($this->healthStatus === SourceHealth::Disabled) {
            $this->enabled = false;
        }
    }
}
