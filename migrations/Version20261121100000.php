<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Slider d'images de l'accueil : table home_banner et section d'accueil « banner » insérée avant « Dernières actualités ».";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE home_banner (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, link_url VARCHAR(500) DEFAULT NULL, position INT NOT NULL, enabled TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        // Place la section juste avant « Dernières actualités » (sinon en fin de
        // liste) en décalant d'un cran les sections qui la suivent.
        $this->addSql("UPDATE home_section SET position = position + 1 WHERE position >= COALESCE((SELECT p FROM (SELECT MIN(position) AS p FROM home_section WHERE type = 'latest_news') AS v), 2147483647) AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM home_section WHERE type = 'banner') AS r)");
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) SELECT 'banner', COALESCE((SELECT MIN(position) - 1 FROM home_section WHERE type = 'latest_news'), (SELECT COALESCE(MAX(position), -1) + 1 FROM home_section)), 1, NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM home_section WHERE type = 'banner')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM home_section WHERE type = 'banner'");
        $this->addSql('DROP TABLE home_banner');
    }
}
