<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Article\Entity\Article;
use App\Notification\ValueObject\DeliveryStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_log')]
#[ORM\Index(name: 'idx_notification_log_rule_sent', columns: ['alert_rule_id', 'sent_at'])]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AlertRule $alertRule;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Article $article;

    #[ORM\Column(length: 50)]
    private string $matchType;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transport = null;

    #[ORM\Column]
    private bool $success;

    #[ORM\Column(length: 20, enumType: DeliveryStatus::class, options: [
        'default' => 'sent',
    ])]
    private DeliveryStatus $deliveryStatus = DeliveryStatus::Sent;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $aiSeverity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiExplanation = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $aiModelUsed = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        AlertRule $alertRule,
        Article $article,
        string $matchType,
        bool $success,
        \DateTimeImmutable $sentAt,
    ) {
        $this->alertRule = $alertRule;
        $this->article = $article;
        $this->matchType = $matchType;
        $this->success = $success;
        $this->sentAt = $sentAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlertRule(): AlertRule
    {
        return $this->alertRule;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getMatchType(): string
    {
        return $this->matchType;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function setTransport(?string $transport): void
    {
        $this->transport = $transport;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getDeliveryStatus(): DeliveryStatus
    {
        return $this->deliveryStatus;
    }

    public function setDeliveryStatus(DeliveryStatus $deliveryStatus): void
    {
        $this->deliveryStatus = $deliveryStatus;
    }

    public function getAiSeverity(): ?int
    {
        return $this->aiSeverity;
    }

    public function setAiSeverity(?int $aiSeverity): void
    {
        $this->aiSeverity = $aiSeverity;
    }

    public function getAiExplanation(): ?string
    {
        return $this->aiExplanation;
    }

    public function setAiExplanation(?string $aiExplanation): void
    {
        $this->aiExplanation = $aiExplanation;
    }

    public function getAiModelUsed(): ?string
    {
        return $this->aiModelUsed;
    }

    public function setAiModelUsed(?string $aiModelUsed): void
    {
        $this->aiModelUsed = $aiModelUsed;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
