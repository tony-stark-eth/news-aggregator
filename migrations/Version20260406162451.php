<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406162451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add article_titles to digest_log and cascade delete from digest_config';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE digest_log DROP CONSTRAINT fk_aa12a62058a929e0');
        $this->addSql('ALTER TABLE digest_log ADD article_titles JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE digest_log ADD CONSTRAINT FK_AA12A62058A929E0 FOREIGN KEY (digest_config_id) REFERENCES digest_config (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE digest_log DROP CONSTRAINT FK_AA12A62058A929E0');
        $this->addSql('ALTER TABLE digest_log DROP article_titles');
        $this->addSql('ALTER TABLE digest_log ADD CONSTRAINT fk_aa12a62058a929e0 FOREIGN KEY (digest_config_id) REFERENCES digest_config (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
