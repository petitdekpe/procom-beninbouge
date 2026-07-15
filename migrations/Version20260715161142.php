<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715161142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statistiques : visitorId/durationSeconds sur article_view, tables article_share et article_impression, commentaires anonymes (author_name/author_email au lieu de author_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article_impression (id INT AUTO_INCREMENT NOT NULL, context VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_188260307294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE article_share (id INT AUTO_INCREMENT NOT NULL, channel VARCHAR(20) NOT NULL, visitor_id VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_98B1EF4B7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_impression ADD CONSTRAINT FK_188260307294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_share ADD CONSTRAINT FK_98B1EF4B7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_view ADD visitor_id VARCHAR(64) DEFAULT NULL, ADD duration_seconds INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526CF675F31B`');
        $this->addSql('DROP INDEX IDX_9474526CF675F31B ON comment');
        $this->addSql('ALTER TABLE comment ADD author_name VARCHAR(100) NOT NULL, ADD author_email VARCHAR(180) DEFAULT NULL, DROP author_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_impression DROP FOREIGN KEY FK_188260307294869C');
        $this->addSql('ALTER TABLE article_share DROP FOREIGN KEY FK_98B1EF4B7294869C');
        $this->addSql('DROP TABLE article_impression');
        $this->addSql('DROP TABLE article_share');
        $this->addSql('ALTER TABLE article_view DROP visitor_id, DROP duration_seconds');
        $this->addSql('ALTER TABLE comment ADD author_id INT NOT NULL, DROP author_name, DROP author_email');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526CF675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_9474526CF675F31B ON comment (author_id)');
    }
}
