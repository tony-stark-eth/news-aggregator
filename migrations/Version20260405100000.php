<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add language to source, title_original and summary_original to article';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD language VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD title_original TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD summary_original TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP language');
        $this->addSql('ALTER TABLE article DROP title_original');
        $this->addSql('ALTER TABLE article DROP summary_original');
    }
}
