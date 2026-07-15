<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715131256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rubriques : couleur sur category (avec rétro-remplissage), media.category devient une relation many-to-many (media_category), seed des rubriques manquantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media_category (media_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_92D3773EA9FDD75 (media_id), INDEX IDX_92D377312469DE2 (category_id), PRIMARY KEY (media_id, category_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_category ADD CONSTRAINT FK_92D3773EA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_category ADD CONSTRAINT FK_92D377312469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');

        // color is added nullable first so the pre-existing rows (created
        // before this feature existed) can be backfilled, then tightened
        // to NOT NULL to match Category::$color's mapping.
        $this->addSql('ALTER TABLE category ADD color VARCHAR(7) DEFAULT NULL');
        $this->addSql("UPDATE category SET color = '#CF3732' WHERE slug = 'actualites'");
        $this->addSql("UPDATE category SET color = '#2B6B43' WHERE slug = 'sport'");
        $this->addSql("UPDATE category SET color = '#A83279' WHERE slug = 'culture'");
        $this->addSql("UPDATE category SET color = '#00897B' WHERE slug = 'sante'");
        $this->addSql("UPDATE category SET color = '#0E3F51' WHERE color IS NULL");
        $this->addSql('ALTER TABLE category CHANGE color color VARCHAR(7) NOT NULL');

        // Rubriques listées par l'utilisateur, hors celles qui existaient déjà
        // (Sport, Culture, Santé, ci-dessus).
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Bénin', 'benin', '#0E3F51', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Politique', 'politique', '#6B2C91', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Économie', 'economie', '#B8860B', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('People', 'people', '#C2185B', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Technologie', 'technologie', '#1565C0', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('International', 'international', '#37474F', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Religion', 'religion', '#8D6E63', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Éducation', 'education', '#F57C00', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Justice', 'justice', '#4527A0', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Société', 'societe', '#5D4037', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Environnement', 'environnement', '#33691E', NULL)");
        $this->addSql("INSERT INTO category (name, slug, color, parent_id) VALUES ('Agriculture', 'agriculture', '#827717', NULL)");

        // media.category_id had no data yet (feature just introduced, no
        // media had ever been assigned a category) — safe to drop outright.
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY `FK_6A2CA10C12469DE2`');
        $this->addSql('DROP INDEX IDX_6A2CA10C12469DE2 ON media');
        $this->addSql('ALTER TABLE media DROP category_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM category WHERE slug IN ('benin', 'politique', 'economie', 'people', 'technologie', 'international', 'religion', 'education', 'justice', 'societe', 'environnement', 'agriculture')");
        $this->addSql('ALTER TABLE media_category DROP FOREIGN KEY FK_92D3773EA9FDD75');
        $this->addSql('ALTER TABLE media_category DROP FOREIGN KEY FK_92D377312469DE2');
        $this->addSql('DROP TABLE media_category');
        $this->addSql('ALTER TABLE category DROP color');
        $this->addSql('ALTER TABLE media ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT `FK_6A2CA10C12469DE2` FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_6A2CA10C12469DE2 ON media (category_id)');
    }
}
