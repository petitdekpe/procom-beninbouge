<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page Audience : TikTok et LinkedIn (liens et nombre d\'abonnés).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD tiktok_url VARCHAR(255) DEFAULT NULL, ADD linkedin_url VARCHAR(255) DEFAULT NULL, ADD tiktok_followers INT DEFAULT NULL, ADD linkedin_followers INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings DROP tiktok_url, DROP linkedin_url, DROP tiktok_followers, DROP linkedin_followers');
    }
}
