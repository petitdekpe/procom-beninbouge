<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Makes Media::article nullable (videos imported automatically from the
 * YouTube channel aren't attached to one) and adds youtubeVideoId, used to
 * avoid re-importing the same video on every sync run.
 */
final class Version20260714120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Media.article nullable + youtube_video_id column (YouTube channel auto-import)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media CHANGE article_id article_id INT DEFAULT NULL, ADD youtube_video_id VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2CA10CF4364E90 ON media (youtube_video_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6A2CA10CF4364E90 ON media');
        $this->addSql('ALTER TABLE media CHANGE article_id article_id INT NOT NULL, DROP youtube_video_id');
    }
}
