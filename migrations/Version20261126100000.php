<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page Audience : chiffres saisis dans les réglages (portée, profil des lecteurs, abonnés par réseau, source).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings ADD audience_monthly_reach INT DEFAULT NULL, ADD audience_under35_percent INT DEFAULT NULL, ADD audience_benin_percent INT DEFAULT NULL, ADD audience_diaspora_percent INT DEFAULT NULL, ADD facebook_followers INT DEFAULT NULL, ADD instagram_followers INT DEFAULT NULL, ADD youtube_subscribers INT DEFAULT NULL, ADD twitter_followers INT DEFAULT NULL, ADD whatsapp_members INT DEFAULT NULL, ADD audience_source VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_settings DROP audience_monthly_reach, DROP audience_under35_percent, DROP audience_benin_percent, DROP audience_diaspora_percent, DROP facebook_followers, DROP instagram_followers, DROP youtube_subscribers, DROP twitter_followers, DROP whatsapp_members, DROP audience_source');
    }
}
