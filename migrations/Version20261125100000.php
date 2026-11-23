<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documenthèque : table document (fichiers téléchargeables par lien direct) et documents joints aux articles (article_document).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, file_path VARCHAR(255) NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(120) DEFAULT NULL, size INT DEFAULT NULL, download_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D8698A76989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_document (article_id INT NOT NULL, document_id INT NOT NULL, INDEX IDX_8D817E1D7294869C (article_id), INDEX IDX_8D817E1DC33F7837 (document_id), PRIMARY KEY (article_id, document_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_document ADD CONSTRAINT FK_8D817E1D7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_document ADD CONSTRAINT FK_8D817E1DC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_document DROP FOREIGN KEY FK_8D817E1D7294869C');
        $this->addSql('ALTER TABLE article_document DROP FOREIGN KEY FK_8D817E1DC33F7837');
        $this->addSql('DROP TABLE article_document');
        $this->addSql('DROP TABLE document');
    }
}
