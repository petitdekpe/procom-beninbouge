<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Photo de profil proposée par un journaliste, en attente de validation.
 */
final class Version20261129100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auteurs : colonne pending_avatar (photo en attente de validation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE author ADD pending_avatar VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE author DROP pending_avatar');
    }
}
