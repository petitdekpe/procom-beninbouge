<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715133523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Page d'accueil configurable : table home_section, seed des 5 sections dans leur ordre par défaut";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE home_section (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, position INT NOT NULL, enabled TINYINT NOT NULL, UNIQUE INDEX UNIQ_9853B1B78CDE5729 (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        $this->addSql("INSERT INTO home_section (type, position, enabled) VALUES ('hero_slider', 0, 1)");
        $this->addSql("INSERT INTO home_section (type, position, enabled) VALUES ('latest_news', 1, 1)");
        $this->addSql("INSERT INTO home_section (type, position, enabled) VALUES ('popular_articles', 2, 1)");
        $this->addSql("INSERT INTO home_section (type, position, enabled) VALUES ('videos', 3, 1)");
        $this->addSql("INSERT INTO home_section (type, position, enabled) VALUES ('podcasts', 4, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE home_section');
    }
}
