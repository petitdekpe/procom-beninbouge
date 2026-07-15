<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715110115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dossier entity + dossier_article join table (curated article collections)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dossier (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, description LONGTEXT DEFAULT NULL, cover_image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3D48E037989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dossier_article (dossier_id INT NOT NULL, article_id INT NOT NULL, INDEX IDX_4060E75E611C0C56 (dossier_id), INDEX IDX_4060E75E7294869C (article_id), PRIMARY KEY (dossier_id, article_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dossier_article ADD CONSTRAINT FK_4060E75E611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dossier_article ADD CONSTRAINT FK_4060E75E7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossier_article DROP FOREIGN KEY FK_4060E75E611C0C56');
        $this->addSql('ALTER TABLE dossier_article DROP FOREIGN KEY FK_4060E75E7294869C');
        $this->addSql('DROP TABLE dossier');
        $this->addSql('DROP TABLE dossier_article');
    }
}
