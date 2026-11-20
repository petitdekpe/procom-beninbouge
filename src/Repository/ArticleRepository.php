<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleView;
use App\Entity\Category;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Published articles sharing the same category or at least one tag
     * with the given article, most recent first.
     *
     * @return list<Article>
     */
    public function findRelated(Article $article, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.id != :id')
            ->andWhere('a.status = :status')
            ->setParameter('id', $article->getId())
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        $tags = $article->getTags();

        if ($tags->isEmpty()) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $article->getCategory());
        } else {
            $qb->leftJoin('a.tags', 't')
                ->andWhere($qb->expr()->orX('a.category = :category', 't IN (:tags)'))
                ->setParameter('category', $article->getCategory())
                ->setParameter('tags', $tags)
                ->groupBy('a.id');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Latest published articles, most recent first.
     *
     * @param list<int> $excludedIds
     *
     * @return list<Article>
     */
    public function findLatestPublished(int $limit, array $excludedIds = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $excludedIds) {
            $qb->andWhere('a.id NOT IN (:excludedIds)')->setParameter('excludedIds', $excludedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Published articles marked "featured" ("À la une"), most recent
     * first — feeds the homepage hero slider (App\Controller\HomeController).
     * Backfills with the latest published articles when there aren't
     * enough featured ones, so the slider is never emptier than $limit
     * allows.
     *
     * @return list<Article>
     */
    public function findFeaturedPublished(int $limit): array
    {
        $featured = $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.featured = true')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (\count($featured) >= $limit) {
            return $featured;
        }

        $excludedIds = array_map(static fn (Article $a) => $a->getId(), $featured);

        return array_merge($featured, $this->findLatestPublished($limit - \count($featured), $excludedIds));
    }

    /**
     * Published articles ordered by total view count (App\Entity\ArticleView),
     * most viewed first — feeds the homepage "Articles populaires" section.
     * Unviewed articles (0 rows in article_view) are still included, via
     * the left join, so a freshly published article isn't excluded outright.
     *
     * @param list<int> $excludedIds articles already shown elsewhere on the page
     *
     * @return list<Article>
     */
    public function findMostViewedPublished(int $limit, array $excludedIds = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('COUNT(v.id) AS HIDDEN viewsCount')
            ->leftJoin(ArticleView::class, 'v', 'WITH', 'v.article = a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->groupBy('a.id')
            ->orderBy('viewsCount', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $excludedIds) {
            $qb->andWhere('a.id NOT IN (:excludedIds)')->setParameter('excludedIds', $excludedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Titles for the homepage "En continu" ticker: articles pinned in the
     * admin ("Épinglé (bandeau alerte)") first, then the most recent ones.
     *
     * @return list<Article>
     */
    public function findTickerPublished(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.pinned', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest published articles in a given rubrique, most recent first —
     * for the public rubrique page (App\Controller\CategoryController).
     *
     * @return list<Article>
     */
    public function findLatestPublishedByCategory(Category $category, int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.category = :category')
            ->andWhere('a.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest published articles carrying a given tag, most recent first —
     * for the public tag page (App\Controller\TagController).
     *
     * @return list<Article>
     */
    public function findLatestPublishedByTag(Tag $tag, int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.tags', 't')
            ->andWhere('t = :tag')
            ->andWhere('a.status = :status')
            ->setParameter('tag', $tag)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Number of published articles, for the homepage's key-figures band.
     */
    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Article count per status (trashed articles excluded by the
     * soft_delete filter), for the admin dashboard. Every status is
     * present in the result, at 0 when no article has it.
     *
     * @return array<string, int> keyed by ArticleStatus value
     */
    public function countByStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (ArticleStatus $s) => $s->value, ArticleStatus::cases()), 0);

        $rows = $this->createQueryBuilder('a')
            ->select('a.status AS status, COUNT(a.id) AS total')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $status = $row['status'] instanceof ArticleStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Number of articles published between $from (inclusive) and $to
     * (exclusive) — for the dashboard's "publiés cette semaine" figure.
     */
    public function countPublishedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt >= :from AND a.publishedAt < :to')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Scheduled articles still to come, soonest first — for the dashboard.
     *
     * @return list<Article>
     */
    public function findUpcomingScheduled(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('c')
            ->leftJoin('a.category', 'c')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->orderBy('a.publishedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Most recently modified articles, any status — for the dashboard's
     * "Derniers articles modifiés" list.
     *
     * @return list<Article>
     */
    public function findRecentlyUpdated(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('c', 'au')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.author', 'au')
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Titles of the given articles in one query (trashed ones are left
     * out by the soft_delete filter) — for the dashboard activity log.
     *
     * @param list<int> $ids
     *
     * @return array<int, string> title keyed by article id
     */
    public function findTitlesByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.id AS id, a.title AS title')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getResult();

        return array_column($rows, 'title', 'id');
    }

    /**
     * Scheduled articles whose publication date has arrived — due to go
     * live (see App\Command\PublishScheduledArticlesCommand).
     *
     * @return list<Article>
     */
    public function findDueForScheduledPublication(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt IS NOT NULL')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trashed articles (see App\Doctrine\SoftDeleteFilter), most recently
     * deleted first — for the "Corbeille" admin view. The soft_delete
     * filter hides these from every other query by design, so it has to
     * be turned off here to find them at all.
     *
     * @return list<Article>
     */
    public function findTrashed(): array
    {
        return $this->withSoftDeleteFilterDisabled(fn () => $this->createQueryBuilder('a')
            ->andWhere('a.deletedAt IS NOT NULL')
            ->orderBy('a.deletedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    /**
     * Number of trashed articles, for the "Corbeille" badge on the main
     * article list — see findTrashed() for the filter caveat.
     */
    public function countTrashed(): int
    {
        return (int) $this->withSoftDeleteFilterDisabled(fn () => $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.deletedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult());
    }

    /**
     * Fetches a single trashed article by id (for restore / permanent
     * delete) — same reasoning as findTrashed() about the filter.
     */
    public function findTrashedById(int $id): ?Article
    {
        return $this->withSoftDeleteFilterDisabled(fn () => $this->createQueryBuilder('a')
            ->andWhere('a.id = :id')
            ->andWhere('a.deletedAt IS NOT NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult());
    }

    private function withSoftDeleteFilterDisabled(callable $query): mixed
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');

        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $query();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
