<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715155301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Page d'accueil : 3 sections « rubrique en avant » configurables (catégorie au choix), type n'est plus unique";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9853B1B78CDE5729 ON home_section');
        $this->addSql('ALTER TABLE home_section ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE home_section ADD CONSTRAINT FK_9853B1B712469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('CREATE INDEX IDX_9853B1B712469DE2 ON home_section (category_id)');

        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) VALUES ('category_highlight', 5, 1, NULL)");
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) VALUES ('category_highlight', 6, 1, NULL)");
        $this->addSql("INSERT INTO home_section (type, position, enabled, category_id) VALUES ('category_highlight', 7, 1, NULL)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM home_section WHERE type = 'category_highlight'");
        $this->addSql('ALTER TABLE home_section DROP FOREIGN KEY FK_9853B1B712469DE2');
        $this->addSql('DROP INDEX IDX_9853B1B712469DE2 ON home_section');
        $this->addSql('ALTER TABLE home_section DROP category_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9853B1B78CDE5729 ON home_section (type)');
    }
}
