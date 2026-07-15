<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleImpression;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleImpression>
 */
class ArticleImpressionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleImpression::class);
    }

    public function countByArticle(Article $article): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, int> impression count keyed by article id, only
     *                          for articles with at least one impression
     */
    public function countByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.article) AS articleId, COUNT(i.id) AS impressionCount')
            ->groupBy('i.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (int) $row['impressionCount'];
        }

        return $map;
    }
}
