<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410195004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pgvector extension and add embedding column to article';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('ALTER TABLE article ADD embedding vector(1536) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP embedding');
        $this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
