<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create chat_messages table for conversation persistence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE chat_messages (
                id BIGSERIAL PRIMARY KEY,
                conversation_id VARCHAR(64) NOT NULL,
                messages TEXT NOT NULL,
                added_at INTEGER NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_chat_messages_conversation ON chat_messages (conversation_id, added_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE chat_messages');
    }
}
