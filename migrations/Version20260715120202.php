<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715120202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ArticleRevision (Historique des modifications) — snapshot on every save';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_revision (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, chapo LONGTEXT DEFAULT NULL, content LONGTEXT NOT NULL, category_name VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, edited_by_id INT DEFAULT NULL, INDEX IDX_388BE1A77294869C (article_id), INDEX IDX_388BE1A7DD7B2EBC (edited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE article_revision ADD CONSTRAINT FK_388BE1A77294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_revision ADD CONSTRAINT FK_388BE1A7DD7B2EBC FOREIGN KEY (edited_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_revision DROP FOREIGN KEY FK_388BE1A77294869C');
        $this->addSql('ALTER TABLE article_revision DROP FOREIGN KEY FK_388BE1A7DD7B2EBC');
        $this->addSql('DROP TABLE article_revision');
    }
}
