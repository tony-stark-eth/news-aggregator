<?php

declare(strict_types=1);

namespace App\Shared\AI\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'model_quality_stat')]
class ModelQualityStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $modelId;

    #[ORM\Column(type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $accepted = 0;

    #[ORM\Column(type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $rejected = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $modelId, \DateTimeImmutable $updatedAt)
    {
        $this->modelId = $modelId;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function getAccepted(): int
    {
        return $this->accepted;
    }

    public function getRejected(): int
    {
        return $this->rejected;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function incrementAccepted(\DateTimeImmutable $now): void
    {
        $this->accepted++;
        $this->updatedAt = $now;
    }

    public function incrementRejected(\DateTimeImmutable $now): void
    {
        $this->rejected++;
        $this->updatedAt = $now;
    }
}
