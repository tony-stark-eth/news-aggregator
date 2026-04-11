<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sentiment_score column to article entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD sentiment_score DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_article_sentiment_score ON article (sentiment_score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_article_sentiment_score');
        $this->addSql('ALTER TABLE article DROP sentiment_score');
    }
}
