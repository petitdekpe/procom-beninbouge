<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619132700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_9474526C7294869C (article_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poll (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(255) NOT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, article_id INT DEFAULT NULL, INDEX IDX_84BCFA457294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poll_option (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, votes_count INT NOT NULL, poll_id INT NOT NULL, INDEX IDX_B68343EB3C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poll_vote (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, poll_id INT NOT NULL, option_id INT NOT NULL, voter_id INT NOT NULL, INDEX IDX_ED568EBE3C947C0F (poll_id), INDEX IDX_ED568EBEA7C41D6F (option_id), INDEX IDX_ED568EBEEBB4B8AD (voter_id), UNIQUE INDEX uniq_poll_vote_poll_voter (poll_id, voter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_A4D707F77294869C (article_id), INDEX IDX_A4D707F7F675F31B (author_id), UNIQUE INDEX uniq_reaction_article_author (article_id, author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE submission (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, location VARCHAR(255) DEFAULT NULL, event_date DATETIME DEFAULT NULL, submitter_name VARCHAR(150) NOT NULL, submitter_email VARCHAR(180) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(100) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA457294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_B68343EB3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBE3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBEA7C41D6F FOREIGN KEY (option_id) REFERENCES poll_option (id)');
        $this->addSql('ALTER TABLE poll_vote ADD CONSTRAINT FK_ED568EBEEBB4B8AD FOREIGN KEY (voter_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F77294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F7F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7294869C');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA457294869C');
        $this->addSql('ALTER TABLE poll_option DROP FOREIGN KEY FK_B68343EB3C947C0F');
        $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_ED568EBE3C947C0F');
        $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_ED568EBEA7C41D6F');
        $this->addSql('ALTER TABLE poll_vote DROP FOREIGN KEY FK_ED568EBEEBB4B8AD');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F77294869C');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F7F675F31B');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE poll');
        $this->addSql('DROP TABLE poll_option');
        $this->addSql('DROP TABLE poll_vote');
        $this->addSql('DROP TABLE reaction');
        $this->addSql('DROP TABLE submission');
        $this->addSql('DROP TABLE user');
    }
}
