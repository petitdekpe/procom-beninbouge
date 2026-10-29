<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924164142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Équipe de la page À propos : table team_member, saisie dans le back-office (Admin\TeamMemberController)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE team_member (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(100) NOT NULL, role VARCHAR(100) NOT NULL, photo VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, linkedin_url VARCHAR(255) DEFAULT NULL, twitter_url VARCHAR(255) DEFAULT NULL, facebook_url VARCHAR(255) DEFAULT NULL, position INT NOT NULL, visible TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE team_member');
    }
}
