<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715144327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SEO : description + meta_title/meta_description sur category, meta_title/meta_description sur dossier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD description LONGTEXT DEFAULT NULL, ADD meta_title VARCHAR(70) DEFAULT NULL, ADD meta_description VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE dossier ADD meta_title VARCHAR(70) DEFAULT NULL, ADD meta_description VARCHAR(200) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP description, DROP meta_title, DROP meta_description');
        $this->addSql('ALTER TABLE dossier DROP meta_title, DROP meta_description');
    }
}
