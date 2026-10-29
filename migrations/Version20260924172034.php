<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924172034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Annonces & Opportunités : table announcement, tarifs dans site_settings, section d'accueil « announcements » insérée juste avant « Nous joindre »";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(12) NOT NULL, tracking_token VARCHAR(64) NOT NULL, category VARCHAR(30) NOT NULL, title VARCHAR(120) NOT NULL, organization VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, location VARCHAR(120) DEFAULT NULL, deadline DATETIME DEFAULT NULL, external_url VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, contact_name VARCHAR(100) NOT NULL, contact_email VARCHAR(180) NOT NULL, contact_phone VARCHAR(20) NOT NULL, show_contact TINYINT NOT NULL, duration_days INT NOT NULL, with_home_card TINYINT NOT NULL, with_popup TINYINT NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, transaction_reference VARCHAR(64) DEFAULT NULL, paid_at DATETIME DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, reviewed_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4DB9D91CAEA34913 (reference), UNIQUE INDEX UNIQ_4DB9D91CB46BB896 (tracking_token), UNIQUE INDEX UNIQ_4DB9D91CED84D250 (transaction_reference), INDEX IDX_4DB9D91CFC6B21F1 (reviewed_by_id), INDEX idx_announcement_status_ends (status, ends_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE site_settings ADD announcement_daily_price INT DEFAULT 1000 NOT NULL, ADD announcement_home_card_daily_price INT DEFAULT 1000 NOT NULL, ADD announcement_popup_daily_price INT DEFAULT 2000 NOT NULL, ADD announcement_min_days INT DEFAULT 3 NOT NULL, ADD announcement_max_days INT DEFAULT 60 NOT NULL');
        // Section d'accueil « Annonces & Opportunités », activée, juste avant « Nous joindre »
        // (ou en dernière position si la section contact n'existe pas).
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) SELECT 'announcements', COALESCE((SELECT position FROM home_section WHERE type = 'contact' LIMIT 1), (SELECT COALESCE(MAX(position), -1) + 1 FROM home_section)), 1, NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM home_section WHERE type = 'announcements')");
        $this->addSql("UPDATE home_section SET position = position + 1 WHERE type = 'contact'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM home_section WHERE type = 'announcements'");
        $this->addSql("UPDATE home_section SET position = position - 1 WHERE type = 'contact'");
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91CFC6B21F1');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('ALTER TABLE site_settings DROP announcement_daily_price, DROP announcement_home_card_daily_price, DROP announcement_popup_daily_price, DROP announcement_min_days, DROP announcement_max_days');
    }
}
