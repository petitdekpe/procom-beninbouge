<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètres du site : Google Analytics 4 et validation Google Search Console.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD google_analytics_id VARCHAR(32) DEFAULT NULL, ADD google_site_verification VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings DROP google_analytics_id, DROP google_site_verification');
    }
}
