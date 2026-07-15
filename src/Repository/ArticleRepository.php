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
     * @return list<Article>
     */
    public function findMostViewedPublished(int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('COUNT(v.id) AS HIDDEN viewsCount')
            ->leftJoin(ArticleView::class, 'v', 'WITH', 'v.article = a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->groupBy('a.id')
            ->orderBy('viewsCount', 'DESC')
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
