<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suppression définitive d'un article (corbeille) : les tables liées doivent
 * suivre au lieu de bloquer avec une violation de clé étrangère (1451).
 * Données dépendantes (vues, partages, commentaires…) : ON DELETE CASCADE.
 * Éléments autonomes (média, galerie, sondage, emplacement pub) : ON DELETE SET NULL.
 */
final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ON DELETE CASCADE / SET NULL sur les clés étrangères vers article';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_impression DROP FOREIGN KEY FK_188260307294869C');
        $this->addSql('ALTER TABLE article_impression ADD CONSTRAINT FK_188260307294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_share DROP FOREIGN KEY FK_98B1EF4B7294869C');
        $this->addSql('ALTER TABLE article_share ADD CONSTRAINT FK_98B1EF4B7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_translation DROP FOREIGN KEY FK_2EEA2F087294869C');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F087294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_view DROP FOREIGN KEY FK_4EBF2C8F7294869C');
        $this->addSql('ALTER TABLE article_view ADD CONSTRAINT FK_4EBF2C8F7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7294869C');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F77294869C');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F77294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_slot DROP FOREIGN KEY FK_14C172027294869C');
        $this->addSql('ALTER TABLE ad_slot ADD CONSTRAINT FK_14C172027294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783A7294869C');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783A7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C7294869C');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA457294869C');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA457294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article_impression DROP FOREIGN KEY FK_188260307294869C');
        $this->addSql('ALTER TABLE article_impression ADD CONSTRAINT FK_188260307294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_share DROP FOREIGN KEY FK_98B1EF4B7294869C');
        $this->addSql('ALTER TABLE article_share ADD CONSTRAINT FK_98B1EF4B7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_translation DROP FOREIGN KEY FK_2EEA2F087294869C');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F087294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_view DROP FOREIGN KEY FK_4EBF2C8F7294869C');
        $this->addSql('ALTER TABLE article_view ADD CONSTRAINT FK_4EBF2C8F7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C7294869C');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE reaction DROP FOREIGN KEY FK_A4D707F77294869C');
        $this->addSql('ALTER TABLE reaction ADD CONSTRAINT FK_A4D707F77294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE ad_slot DROP FOREIGN KEY FK_14C172027294869C');
        $this->addSql('ALTER TABLE ad_slot ADD CONSTRAINT FK_14C172027294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783A7294869C');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783A7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C7294869C');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C7294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA457294869C');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA457294869C FOREIGN KEY (article_id) REFERENCES article (id)');
    }
}
