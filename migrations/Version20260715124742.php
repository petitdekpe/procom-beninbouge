<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715124742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gestion multimédia : description/auteur/licence/mots-clés/catégorie/documentUrl sur Media + table media_tag';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE media_tag (media_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_48D8C57EEA9FDD75 (media_id), INDEX IDX_48D8C57EBAD26311 (tag_id), PRIMARY KEY (media_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_tag ADD CONSTRAINT FK_48D8C57EEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_tag ADD CONSTRAINT FK_48D8C57EBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media ADD description LONGTEXT DEFAULT NULL, ADD license VARCHAR(20) DEFAULT NULL, ADD document_url VARCHAR(255) DEFAULT NULL, ADD author_id INT DEFAULT NULL, ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10CF675F31B FOREIGN KEY (author_id) REFERENCES author (id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_6A2CA10CF675F31B ON media (author_id)');
        $this->addSql('CREATE INDEX IDX_6A2CA10C12469DE2 ON media (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE media_tag DROP FOREIGN KEY FK_48D8C57EEA9FDD75');
        $this->addSql('ALTER TABLE media_tag DROP FOREIGN KEY FK_48D8C57EBAD26311');
        $this->addSql('DROP TABLE media_tag');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10CF675F31B');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C12469DE2');
        $this->addSql('DROP INDEX IDX_6A2CA10CF675F31B ON media');
        $this->addSql('DROP INDEX IDX_6A2CA10C12469DE2 ON media');
        $this->addSql('ALTER TABLE media DROP description, DROP license, DROP document_url, DROP author_id, DROP category_id');
    }
}
