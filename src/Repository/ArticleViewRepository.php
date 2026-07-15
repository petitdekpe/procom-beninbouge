<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleView>
 */
class ArticleViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleView::class);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{source: string|null, count: int}>
     */
    public function countBySource(): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.source AS source, COUNT(v.id) AS count')
            ->groupBy('v.source')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['source' => $row['source'], 'count' => (int) $row['count']],
            $rows,
        );
    }

    /**
     * @return list<array{id: int, title: string, slug: string, viewsCount: int}>
     */
    public function topArticles(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('a.id AS id, a.title AS title, a.slug AS slug, COUNT(v.id) AS viewsCount')
            ->join('v.article', 'a')
            ->groupBy('a.id')
            ->orderBy('viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'viewsCount' => (int) $row['viewsCount'],
            ],
            $rows,
        );
    }

    /**
     * Total unique visitors across the whole site — visits with no
     * visitorId (cookies blocked) are excluded, same best-effort
     * reasoning as countUniqueByArticleMap().
     */
    public function countUniqueTotal(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.visitorId)')
            ->andWhere('v.visitorId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Average real time-on-page across every view that reported one (see
     * ArticleView::$durationSeconds) — instant bounces that never fired
     * the beacon aren't counted, so this reflects "among readers who
     * stayed at least a moment," not literally everyone who loaded the
     * page.
     */
    public function averageDurationTotal(): ?float
    {
        $result = $this->createQueryBuilder('v')
            ->select('AVG(v.durationSeconds)')
            ->andWhere('v.durationSeconds IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : null;
    }

    /**
     * @return array<int, int> view count keyed by article id, only for
     *                          articles with at least one view
     */
    public function countByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.article) AS articleId, COUNT(v.id) AS viewCount')
            ->groupBy('v.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (int) $row['viewCount'];
        }

        return $map;
    }

    /**
     * @return array<int, int> distinct visitorId count keyed by article
     *                          id — see ArticleView::$visitorId for the
     *                          "cookies blocked" caveat
     */
    public function countUniqueByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.article) AS articleId, COUNT(DISTINCT v.visitorId) AS uniqueCount')
            ->andWhere('v.visitorId IS NOT NULL')
            ->groupBy('v.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (int) $row['uniqueCount'];
        }

        return $map;
    }

    /**
     * @return array<int, float> average durationSeconds keyed by article
     *                            id, only among views that reported one
     */
    public function averageDurationByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.article) AS articleId, AVG(v.durationSeconds) AS avgDuration')
            ->andWhere('v.durationSeconds IS NOT NULL')
            ->groupBy('v.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (float) $row['avgDuration'];
        }

        return $map;
    }

    /**
     * @return list<array{source: string|null, count: int}>
     */
    public function countBySourceForArticle(Article $article): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('v.source AS source, COUNT(v.id) AS count')
            ->andWhere('v.article = :article')
            ->setParameter('article', $article)
            ->groupBy('v.source')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['source' => $row['source'], 'count' => (int) $row['count']],
            $rows,
        );
    }
}
