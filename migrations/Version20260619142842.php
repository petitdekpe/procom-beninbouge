<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619142842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(300) NOT NULL, chapo LONGTEXT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, article_id INT NOT NULL, UNIQUE INDEX UNIQ_2EEA2F08989D9B62 (slug), INDEX IDX_2EEA2F087294869C (article_id), UNIQUE INDEX uniq_article_translation_article_locale (article_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, description LONGTEXT NOT NULL, meta_title VARCHAR(70) DEFAULT NULL, meta_description VARCHAR(200) DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME DEFAULT NULL, location VARCHAR(255) NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3BAE0AA7989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F087294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article ADD meta_title VARCHAR(70) DEFAULT NULL, ADD meta_description VARCHAR(200) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_translation DROP FOREIGN KEY FK_2EEA2F087294869C');
        $this->addSql('DROP TABLE article_translation');
        $this->addSql('DROP TABLE event');
        $this->addSql('ALTER TABLE article DROP meta_title, DROP meta_description');
    }
}
