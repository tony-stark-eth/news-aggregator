<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410094300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source health tracking fields and reliability weight';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD last_error_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD success_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE source ADD failure_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE source ADD reliability_weight DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE source ALTER full_text_enabled DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE source DROP last_error_at');
        $this->addSql('ALTER TABLE source DROP success_count');
        $this->addSql('ALTER TABLE source DROP failure_count');
        $this->addSql('ALTER TABLE source DROP reliability_weight');
        $this->addSql('ALTER TABLE source ALTER full_text_enabled SET DEFAULT true');
    }
}
