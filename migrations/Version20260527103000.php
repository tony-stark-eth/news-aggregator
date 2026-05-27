<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cascade delete articles (and related rows) when a feed source is removed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_log DROP CONSTRAINT fk_ed15df27294869c');
        $this->addSql('ALTER TABLE user_article_bookmark DROP CONSTRAINT fk_f29f24ab7294869c');
        $this->addSql('ALTER TABLE user_article_read DROP CONSTRAINT fk_a8d9ba647294869c');
        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e66953c1c61');

        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_article_read ADD CONSTRAINT FK_A8D9BA647294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_article_bookmark ADD CONSTRAINT FK_F29F24AB7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF27294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_log DROP CONSTRAINT fk_ed15df27294869c');
        $this->addSql('ALTER TABLE user_article_bookmark DROP CONSTRAINT fk_f29f24ab7294869c');
        $this->addSql('ALTER TABLE user_article_read DROP CONSTRAINT fk_a8d9ba647294869c');
        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e66953c1c61');

        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_article_read ADD CONSTRAINT FK_A8D9BA647294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_article_bookmark ADD CONSTRAINT FK_F29F24AB7294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF27294869C FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE');
    }
}
