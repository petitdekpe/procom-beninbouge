<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Paramètres du site : vignette d'aperçu des liens partagés (site_settings.share_image_url).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD share_image_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings DROP share_image_url');
    }
}
