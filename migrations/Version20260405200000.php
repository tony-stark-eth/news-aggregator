<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery_status column to notification_log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE notification_log ADD delivery_status VARCHAR(20) DEFAULT 'sent' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_log DROP delivery_status');
    }
}
