<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620105845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD pending_approval TINYINT NOT NULL DEFAULT 0, ADD requested_status VARCHAR(20) DEFAULT NULL, ADD approved_at DATETIME DEFAULT NULL, ADD approved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E662D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_23A0E662D234F6A ON article (approved_by_id)');
        $this->addSql('ALTER TABLE author ADD pending_first_name VARCHAR(100) DEFAULT NULL, ADD pending_last_name VARCHAR(100) DEFAULT NULL, ADD pending_email VARCHAR(180) DEFAULT NULL, ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE author ADD CONSTRAINT FK_BDAFD8C8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BDAFD8C8A76ED395 ON author (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E662D234F6A');
        $this->addSql('DROP INDEX IDX_23A0E662D234F6A ON article');
        $this->addSql('ALTER TABLE article DROP pending_approval, DROP requested_status, DROP approved_at, DROP approved_by_id');
        $this->addSql('ALTER TABLE author DROP FOREIGN KEY FK_BDAFD8C8A76ED395');
        $this->addSql('DROP INDEX UNIQ_BDAFD8C8A76ED395 ON author');
        $this->addSql('ALTER TABLE author DROP pending_first_name, DROP pending_last_name, DROP pending_email, DROP user_id');
    }
}
