<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category column to model_quality_stat for chat/embedding quality tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE model_quality_stat ADD category VARCHAR(32) NOT NULL DEFAULT 'enrichment'");
        $this->addSql('DROP INDEX IF EXISTS UNIQ_model_quality_stat_model_id');
        $this->addSql('ALTER TABLE model_quality_stat DROP CONSTRAINT IF EXISTS UNIQ_model_quality_stat_model_id');

        // Remove old unique constraint on model_id alone (name varies by Doctrine version)
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                constraint_name TEXT;
            BEGIN
                SELECT conname INTO constraint_name
                FROM pg_constraint
                WHERE conrelid = 'model_quality_stat'::regclass
                  AND contype = 'u'
                  AND array_length(conkey, 1) = 1;
                IF constraint_name IS NOT NULL THEN
                    EXECUTE 'ALTER TABLE model_quality_stat DROP CONSTRAINT ' || constraint_name;
                END IF;
            END $$
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_model_category ON model_quality_stat (model_id, category)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_model_category');
        $this->addSql('ALTER TABLE model_quality_stat DROP COLUMN category');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_model_quality_stat_model_id ON model_quality_stat (model_id)');
    }
}
