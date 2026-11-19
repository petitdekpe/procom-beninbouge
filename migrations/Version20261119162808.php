<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119162808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Annonces : document joint, coordonnées du contact facultatives (annonces de la rédaction).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE announcement ADD document VARCHAR(255) DEFAULT NULL, CHANGE contact_name contact_name VARCHAR(100) DEFAULT NULL, CHANGE contact_email contact_email VARCHAR(180) DEFAULT NULL, CHANGE contact_phone contact_phone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE announcement SET contact_name = COALESCE(contact_name, ''), contact_email = COALESCE(contact_email, ''), contact_phone = COALESCE(contact_phone, '')");
        $this->addSql('ALTER TABLE announcement DROP document, CHANGE contact_name contact_name VARCHAR(100) NOT NULL, CHANGE contact_email contact_email VARCHAR(180) NOT NULL, CHANGE contact_phone contact_phone VARCHAR(20) NOT NULL');
    }
}
