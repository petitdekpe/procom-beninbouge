<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715103853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Category parent/children (sous-catégories) + minor pre-existing schema drift cleanup (article.pending_approval default, media unique index name)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE pending_approval pending_approval TINYINT NOT NULL');
        $this->addSql('ALTER TABLE category ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_64C19C1727ACA70 ON category (parent_id)');
        $this->addSql('ALTER TABLE media RENAME INDEX uniq_6a2ca10cf4364e90 TO UNIQ_6A2CA10C8E06FC7F');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article CHANGE pending_approval pending_approval TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('DROP INDEX IDX_64C19C1727ACA70 ON category');
        $this->addSql('ALTER TABLE category DROP parent_id');
        $this->addSql('ALTER TABLE media RENAME INDEX uniq_6a2ca10c8e06fc7f TO UNIQ_6A2CA10CF4364E90');
    }
}
