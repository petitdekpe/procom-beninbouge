<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924161900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Section « Nous joindre » : tables contact_message et newsletter_subscriber, et section d'accueil de type contact (activée, en dernière position)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(180) DEFAULT NULL, subject VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_401562C3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) SELECT 'contact', COALESCE(MAX(position), -1) + 1, 1, NULL FROM home_section WHERE NOT EXISTS (SELECT 1 FROM home_section WHERE type = 'contact')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM home_section WHERE type = 'contact'");
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE newsletter_subscriber');
    }
}
