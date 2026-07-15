<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleRevision>
 */
class ArticleRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleRevision::class);
    }

    /**
     * Most recent first, for the "Historique" view on an article. Orders
     * by id as well as createdAt: two saves in the same second (an
     * automated flow, or just a fast editor) would otherwise tie on a
     * DATETIME column's one-second resolution and could come back in the
     * wrong order — id is auto-increment, so it always reflects the true
     * insertion order.
     *
     * @return list<ArticleRevision>
     */
    public function findByArticle(Article $article): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.article = :article')
            ->setParameter('article', $article)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
