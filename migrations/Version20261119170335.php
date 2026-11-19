<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119170335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Annonces : lien personnalisé (/annonces/{slug}).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE announcement ADD slug VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4DB9D91C989D9B62 ON announcement (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_4DB9D91C989D9B62 ON announcement');
        $this->addSql('ALTER TABLE announcement DROP slug');
    }
}
