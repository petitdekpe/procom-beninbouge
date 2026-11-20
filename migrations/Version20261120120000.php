<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Récaps détachés des articles : table recap, reprise des articles de type « recap » puis suppression de ceux-ci et de article.video_url.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recap (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, content LONGTEXT NOT NULL, video_url VARCHAR(255) DEFAULT NULL, cover_image VARCHAR(255) DEFAULT NULL, meta_title VARCHAR(70) DEFAULT NULL, meta_description VARCHAR(200) DEFAULT NULL, status VARCHAR(20) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT DEFAULT NULL, author_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_2FBA1D20989D9B62 (slug), INDEX IDX_2FBA1D2012469DE2 (category_id), INDEX IDX_2FBA1D20F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recap ADD CONSTRAINT FK_2FBA1D2012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recap ADD CONSTRAINT FK_2FBA1D20F675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE SET NULL');

        // Reprise des articles déjà créés au format « recap » (première version
        // de la fonctionnalité) : ils deviennent de vrais récaps.
        $this->addSql("INSERT INTO recap (title, slug, content, video_url, cover_image, meta_title, meta_description, status, published_at, created_at, updated_at, category_id, author_id) SELECT title, slug, content, video_url, cover_image, meta_title, meta_description, CASE WHEN status = 'published' THEN 'published' ELSE 'draft' END, published_at, created_at, updated_at, category_id, author_id FROM article WHERE type = 'recap' AND video_url IS NOT NULL");

        // Puis ils quittent la table article (avec leurs éventuelles lignes
        // liées) : ArticleType n'a plus de valeur « recap ».
        foreach (['article_impression', 'article_view', 'article_share', 'article_revision', 'article_tag', 'article_translation', 'comment', 'dossier_article', 'reaction'] as $table) {
            $this->addSql(\sprintf("DELETE FROM %s WHERE article_id IN (SELECT id FROM (SELECT id FROM article WHERE type = 'recap') AS r)", $table));
        }
        $this->addSql("DELETE FROM article WHERE type = 'recap'");

        $this->addSql('ALTER TABLE article DROP video_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD video_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP TABLE recap');
    }
}
