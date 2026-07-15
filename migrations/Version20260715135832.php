<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715135832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Paramètres généraux : table site_settings (singleton) seedée avec les valeurs actuellement codées en dur (logo, tagline, contact) pour ne rien changer visuellement tant que l'admin ne modifie rien";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, site_name VARCHAR(150) NOT NULL, logo_url VARCHAR(255) DEFAULT NULL, logo_footer_url VARCHAR(255) DEFAULT NULL, favicon_url VARCHAR(255) DEFAULT NULL, footer_tagline LONGTEXT DEFAULT NULL, contact_address VARCHAR(255) DEFAULT NULL, contact_phone VARCHAR(50) DEFAULT NULL, contact_email VARCHAR(180) DEFAULT NULL, email_from_name VARCHAR(150) DEFAULT NULL, email_from_address VARCHAR(180) DEFAULT NULL, facebook_url VARCHAR(255) DEFAULT NULL, twitter_url VARCHAR(255) DEFAULT NULL, instagram_url VARCHAR(255) DEFAULT NULL, youtube_url VARCHAR(255) DEFAULT NULL, whatsapp_url VARCHAR(255) DEFAULT NULL, maintenance_mode TINYINT NOT NULL, maintenance_message LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        $this->addSql(<<<'SQL'
            INSERT INTO site_settings (
                site_name, logo_url, logo_footer_url, footer_tagline,
                contact_address, contact_email, maintenance_mode, updated_at
            ) VALUES (
                'Bénin Bouge',
                '/assets/logo-noir-horizontal.png',
                '/assets/logo-blanc-horizontal.png',
                'Le média digital du Bénin qui se transforme et qui bouge. Actu, culture, économie, politique et initiatives locales.',
                'Cotonou, Bénin',
                'contact@beninbouge.com',
                0,
                NOW()
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_settings');
    }
}
