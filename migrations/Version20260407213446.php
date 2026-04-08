<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407213446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD content_full_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD content_full_html TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD full_text_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD full_text_enabled BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP content_full_text');
        $this->addSql('ALTER TABLE article DROP content_full_html');
        $this->addSql('ALTER TABLE article DROP full_text_status');
        $this->addSql('ALTER TABLE source DROP full_text_enabled');
    }
}
