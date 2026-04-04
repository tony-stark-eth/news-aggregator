<?php

declare(strict_types=1);

namespace App\User\Entity;

use App\Article\Entity\Article;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_article_read')]
#[ORM\UniqueConstraint(name: 'uniq_user_article', columns: ['user_id', 'article_id'])]
class UserArticleRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Article $article;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $readAt;

    public function __construct(User $user, Article $article, \DateTimeImmutable $readAt)
    {
        $this->user = $user;
        $this->article = $article;
        $this->readAt = $readAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }
}
