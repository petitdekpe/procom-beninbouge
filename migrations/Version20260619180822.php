<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619180822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ad_slot (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, placement VARCHAR(100) NOT NULL, creative_url VARCHAR(255) DEFAULT NULL, target_url VARCHAR(255) DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, partner_id INT NOT NULL, article_id INT DEFAULT NULL, INDEX IDX_14C172029393F8FE (partner_id), INDEX IDX_14C172027294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE donation (id INT AUTO_INCREMENT NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, transaction_reference VARCHAR(100) DEFAULT NULL, donor_name VARCHAR(150) DEFAULT NULL, donor_email VARCHAR(180) DEFAULT NULL, donor_phone VARCHAR(30) DEFAULT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_31E581A0ED84D250 (transaction_reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE partner (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact_email VARCHAR(180) DEFAULT NULL, contact_phone VARCHAR(30) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, logo_url VARCHAR(255) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ad_slot ADD CONSTRAINT FK_14C172029393F8FE FOREIGN KEY (partner_id) REFERENCES partner (id)');
        $this->addSql('ALTER TABLE ad_slot ADD CONSTRAINT FK_14C172027294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article ADD type VARCHAR(20) NOT NULL, ADD sponsor_id INT DEFAULT NULL');
        $this->addSql("UPDATE article SET type = 'editorial' WHERE type = ''");
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6612F7FB51 FOREIGN KEY (sponsor_id) REFERENCES partner (id)');
        $this->addSql('CREATE INDEX IDX_23A0E6612F7FB51 ON article (sponsor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ad_slot DROP FOREIGN KEY FK_14C172029393F8FE');
        $this->addSql('ALTER TABLE ad_slot DROP FOREIGN KEY FK_14C172027294869C');
        $this->addSql('DROP TABLE ad_slot');
        $this->addSql('DROP TABLE donation');
        $this->addSql('DROP TABLE partner');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6612F7FB51');
        $this->addSql('DROP INDEX IDX_23A0E6612F7FB51 ON article');
        $this->addSql('ALTER TABLE article DROP type, DROP sponsor_id');
    }
}
