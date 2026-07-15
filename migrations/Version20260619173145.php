<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619173145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(20) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT NOT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_FD06F64710DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_view (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_4EBF2C8F7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F64710DAF24A FOREIGN KEY (actor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE article_view ADD CONSTRAINT FK_4EBF2C8F7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F64710DAF24A');
        $this->addSql('ALTER TABLE article_view DROP FOREIGN KEY FK_4EBF2C8F7294869C');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE article_view');
    }
}
