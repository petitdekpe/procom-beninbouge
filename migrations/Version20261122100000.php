<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Rôles et permissions modifiables (table app_role, 4 rôles système), comptes désactivables avec mot de passe temporaire (user.enabled, user.must_change_password), publicités du slider gérées comme les annonces (home_banner : référence, annonceur, période, montant).";
    }

    public function up(Schema $schema): void
    {
        // ----- Rôles et permissions -----
        $this->addSql('CREATE TABLE app_role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(60) NOT NULL, label VARCHAR(80) NOT NULL, description VARCHAR(255) DEFAULT NULL, permissions JSON NOT NULL, is_system TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5247AFCA77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        // Mêmes droits qu'avant pour les rôles existants (un journaliste garde ses accès,
        // le rédacteur en chef les files de relecture, annonces, messages et équipe, etc.).
        $journalist = ['articles.write', 'taxonomy.manage', 'authors.manage', 'comments.moderate', 'media.manage', 'pages.manage', 'recaps.manage', 'home.manage', 'stats.view'];
        $editorInChief = array_merge($journalist, ['workflow.review', 'articles.publish', 'announcements.manage', 'ads.manage', 'contact.manage', 'team.manage']);
        $proofreader = array_merge($journalist, ['workflow.proofread']);
        $admin = array_merge($editorInChief, ['workflow.proofread', 'announcements.pricing', 'validations.manage', 'settings.manage', 'users.manage', 'roles.manage']);

        $roles = [
            ['ROLE_EDITOR', 'Journaliste', 'Écrit et modifie des articles, récaps, médias, catégories et pages.', $journalist],
            ['ROLE_EDITOR_IN_CHIEF', 'Rédacteur en chef', 'Relit et publie, gère les annonces, les publicités, les messages et l\'équipe.', $editorInChief],
            ['ROLE_PROOFREADER', 'Correcteur', 'Corrige les articles envoyés en correction.', $proofreader],
            ['ROLE_ADMIN', 'Administrateur', 'Accès total : validations, utilisateurs, rôles et paramètres du site.', $admin],
        ];
        foreach ($roles as [$code, $label, $description, $permissions]) {
            $this->addSql(
                'INSERT INTO app_role (code, label, description, permissions, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())',
                [$code, $label, $description, json_encode($permissions, \JSON_THROW_ON_ERROR)],
            );
        }

        // ----- Comptes : désactivation et mot de passe temporaire -----
        $this->addSql('ALTER TABLE user ADD enabled TINYINT DEFAULT 1 NOT NULL, ADD must_change_password TINYINT DEFAULT 0 NOT NULL');

        // ----- Publicités du slider -----
        $this->addSql('ALTER TABLE home_banner ADD reference VARCHAR(12) DEFAULT NULL, ADD advertiser VARCHAR(120) DEFAULT NULL, ADD contact_name VARCHAR(100) DEFAULT NULL, ADD contact_email VARCHAR(180) DEFAULT NULL, ADD contact_phone VARCHAR(20) DEFAULT NULL, ADD amount INT DEFAULT 0 NOT NULL, ADD duration_days INT DEFAULT 30 NOT NULL, ADD starts_at DATETIME DEFAULT NULL, ADD ends_at DATETIME DEFAULT NULL');
        // Bannières déjà créées : elles restent en ligne un an, annonceur à renseigner.
        $this->addSql("UPDATE home_banner SET reference = CONCAT('PUB-', LPAD(id, 6, '0')), advertiser = 'À renseigner', duration_days = 365, starts_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 365 DAY)");
        $this->addSql('ALTER TABLE home_banner MODIFY reference VARCHAR(12) NOT NULL, MODIFY advertiser VARCHAR(120) NOT NULL, ALTER amount DROP DEFAULT, ALTER duration_days DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F99D15AAEA34913 ON home_banner (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9F99D15AAEA34913 ON home_banner');
        $this->addSql('ALTER TABLE home_banner DROP reference, DROP advertiser, DROP contact_name, DROP contact_email, DROP contact_phone, DROP amount, DROP duration_days, DROP starts_at, DROP ends_at');
        $this->addSql('ALTER TABLE user DROP enabled, DROP must_change_password');
        $this->addSql('DROP TABLE app_role');
    }
}
