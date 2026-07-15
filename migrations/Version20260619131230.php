<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619131230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gallery (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, article_id INT DEFAULT NULL, INDEX IDX_472B783A7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, title VARCHAR(255) DEFAULT NULL, position INT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, youtube_embed_url VARCHAR(255) DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, audio_url VARCHAR(255) DEFAULT NULL, transcript LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, gallery_id INT DEFAULT NULL, INDEX IDX_6A2CA10C7294869C (article_id), INDEX IDX_6A2CA10C4E7AF8F (gallery_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783A7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C4E7AF8F FOREIGN KEY (gallery_id) REFERENCES gallery (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783A7294869C');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C7294869C');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C4E7AF8F');
        $this->addSql('DROP TABLE gallery');
        $this->addSql('DROP TABLE media');
    }
}
