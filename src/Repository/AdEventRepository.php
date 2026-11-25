<?php

namespace App\Repository;

use App\Entity\AdEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Agrégats de la régie. SQL natif : les SUM conditionnels et COUNT DISTINCT
 * sont plus lisibles qu'en DQL, et la table est lue par groupes (un seul
 * passage par page admin, pas de N+1).
 *
 * @extends ServiceEntityRepository<AdEvent>
 */
class AdEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdEvent::class);
    }

    /**
     * Par publicité : affichages, clics, lecteurs distincts (affichages).
     * $since nul : depuis toujours.
     *
     * @return array<int, array{impressions: int, clicks: int, readers: int}> indexé par id de publicité
     */
    public function statsByBanner(?\DateTimeImmutable $since = null): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT banner_id AS id,
                    SUM(kind = 'impression') AS impressions,
                    SUM(kind = 'click') AS clicks,
                    COUNT(DISTINCT CASE WHEN kind = 'impression' THEN visitor_id END) AS readers
             FROM ad_event
             WHERE banner_id IS NOT NULL".($since ? ' AND created_at >= :since' : '').'
             GROUP BY banner_id',
            $since ? ['since' => $since->format('Y-m-d H:i:s')] : [],
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = [
                'impressions' => (int) $row['impressions'],
                'clicks' => (int) $row['clicks'],
                'readers' => (int) $row['readers'],
            ];
        }

        return $map;
    }

    /**
     * Par annonce : affichages sur les cartes et le pop-up, clics vers la
     * page, puis sur la page même (vues, lecteurs distincts, clics sur un
     * lien de contact ou de candidature).
     *
     * @return array<int, array{impressions: int, clicks: int, views: int, readers: int, actions: int}> indexé par id d'annonce
     */
    public function statsByAnnouncement(?\DateTimeImmutable $since = null): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT announcement_id AS id,
                    SUM(kind = 'impression' AND placement <> 'detail') AS impressions,
                    SUM(kind = 'click' AND placement <> 'detail') AS clicks,
                    SUM(kind = 'impression' AND placement = 'detail') AS views,
                    COUNT(DISTINCT CASE WHEN kind = 'impression' AND placement = 'detail' THEN visitor_id END) AS readers,
                    SUM(kind = 'click' AND placement = 'detail') AS actions
             FROM ad_event
             WHERE announcement_id IS NOT NULL".($since ? ' AND created_at >= :since' : '').'
             GROUP BY announcement_id',
            $since ? ['since' => $since->format('Y-m-d H:i:s')] : [],
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = [
                'impressions' => (int) $row['impressions'],
                'clicks' => (int) $row['clicks'],
                'views' => (int) $row['views'],
                'readers' => (int) $row['readers'],
                'actions' => (int) $row['actions'],
            ];
        }

        return $map;
    }
}
