<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mesure de la régie : table ad_event (affichages et clics des publicités du slider et des annonces).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ad_event (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(20) NOT NULL, placement VARCHAR(30) NOT NULL, visitor_id VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, banner_id INT DEFAULT NULL, announcement_id INT DEFAULT NULL, INDEX IDX_6CE5022684EC833 (banner_id), INDEX IDX_6CE5022913AEA17 (announcement_id), INDEX idx_ad_event_banner (banner_id, kind, created_at), INDEX idx_ad_event_announcement (announcement_id, kind, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ad_event ADD CONSTRAINT FK_6CE5022684EC833 FOREIGN KEY (banner_id) REFERENCES home_banner (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_event ADD CONSTRAINT FK_6CE5022913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ad_event DROP FOREIGN KEY FK_6CE5022684EC833');
        $this->addSql('ALTER TABLE ad_event DROP FOREIGN KEY FK_6CE5022913AEA17');
        $this->addSql('DROP TABLE ad_event');
    }
}
