<?php

declare(strict_types=1);

namespace App\Digest\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'digest_log')]
class DigestLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private DigestConfig $digestConfig;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $articleCount;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column]
    private bool $deliverySuccess;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transport = null;

    public function __construct(
        DigestConfig $digestConfig,
        \DateTimeImmutable $generatedAt,
        int $articleCount,
        string $content,
        bool $deliverySuccess,
    ) {
        $this->digestConfig = $digestConfig;
        $this->generatedAt = $generatedAt;
        $this->articleCount = $articleCount;
        $this->content = $content;
        $this->deliverySuccess = $deliverySuccess;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDigestConfig(): DigestConfig
    {
        return $this->digestConfig;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getArticleCount(): int
    {
        return $this->articleCount;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isDeliverySuccess(): bool
    {
        return $this->deliverySuccess;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function setTransport(?string $transport): void
    {
        $this->transport = $transport;
    }
}
