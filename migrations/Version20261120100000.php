<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Articles « Récap » (vidéo format mobile) : colonne article.video_url et section d'accueil « recap » insérée avant « Vidéos ».";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD video_url VARCHAR(255) DEFAULT NULL');

        // Place la section juste avant « Vidéos » (sinon en fin de liste) en
        // décalant d'un cran les sections qui la suivent.
        $this->addSql("UPDATE home_section SET position = position + 1 WHERE position >= COALESCE((SELECT p FROM (SELECT MIN(position) AS p FROM home_section WHERE type = 'videos') AS v), 2147483647) AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM home_section WHERE type = 'recap') AS r)");
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) SELECT 'recap', COALESCE((SELECT MIN(position) - 1 FROM home_section WHERE type = 'videos'), (SELECT COALESCE(MAX(position), -1) + 1 FROM home_section)), 1, NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM home_section WHERE type = 'recap')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM home_section WHERE type = 'recap'");
        $this->addSql('ALTER TABLE article DROP video_url');
    }
}
