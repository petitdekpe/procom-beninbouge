<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Application mobile : accès des téléphones et protection contre les
 * créations en double. N'ajoute que deux tables.
 */
final class Version20261128100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Application mobile : tables mobile_token et mobile_request_key.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobile_request_key (id INT AUTO_INCREMENT NOT NULL, client_id VARCHAR(64) NOT NULL, target_type VARCHAR(20) NOT NULL, target_id INT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_9B97BF78A76ED395 (user_id), UNIQUE INDEX uniq_mobile_request_key (user_id, client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mobile_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, password_fingerprint VARCHAR(64) NOT NULL, device_name VARCHAR(120) NOT NULL, push_token VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_11D11077B3BC57DA (token_hash), INDEX IDX_11D11077A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mobile_request_key ADD CONSTRAINT FK_9B97BF78A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mobile_token ADD CONSTRAINT FK_11D11077A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mobile_request_key');
        $this->addSql('DROP TABLE mobile_token');
    }
}
