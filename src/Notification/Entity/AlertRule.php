<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Notification\ValueObject\AlertRuleType;
use App\Notification\ValueObject\AlertUrgency;
use App\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'alert_rule')]
class AlertRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 20, enumType: AlertRuleType::class)]
    private AlertRuleType $type;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $keywords = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contextPrompt = null;

    #[ORM\Column(length: 20, enumType: AlertUrgency::class)]
    private AlertUrgency $urgency = AlertUrgency::Medium;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $severityThreshold = 5;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $cooldownMinutes = 60;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        AlertRuleType $type,
        User $user,
        \DateTimeImmutable $createdAt,
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->user = $user;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
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

    public function getType(): AlertRuleType
    {
        return $this->type;
    }

    public function setType(AlertRuleType $type): void
    {
        $this->type = $type;
    }

    /**
     * @return list<string>
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param list<string> $keywords
     */
    public function setKeywords(array $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getContextPrompt(): ?string
    {
        return $this->contextPrompt;
    }

    public function setContextPrompt(?string $contextPrompt): void
    {
        $this->contextPrompt = $contextPrompt;
    }

    public function getUrgency(): AlertUrgency
    {
        return $this->urgency;
    }

    public function setUrgency(AlertUrgency $urgency): void
    {
        $this->urgency = $urgency;
    }

    public function getSeverityThreshold(): int
    {
        return $this->severityThreshold;
    }

    public function setSeverityThreshold(int $severityThreshold): void
    {
        $this->severityThreshold = $severityThreshold;
    }

    public function getCooldownMinutes(): int
    {
        return $this->cooldownMinutes;
    }

    public function setCooldownMinutes(int $cooldownMinutes): void
    {
        $this->cooldownMinutes = $cooldownMinutes;
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param list<string> $categories
     */
    public function setCategories(array $categories): void
    {
        $this->categories = $categories;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function requiresAiEvaluation(): bool
    {
        return $this->type === AlertRuleType::Ai || $this->type === AlertRuleType::Both;
    }
}
