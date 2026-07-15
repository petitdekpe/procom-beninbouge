<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleShare>
 */
class ArticleShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleShare::class);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByArticle(Article $article): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, int> share count keyed by article id, only for
     *                          articles with at least one share
     */
    public function countByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.article) AS articleId, COUNT(s.id) AS shareCount')
            ->groupBy('s.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (int) $row['shareCount'];
        }

        return $map;
    }

    /**
     * @return list<array{channel: string, count: int}>
     */
    public function countByChannel(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.channel AS channel, COUNT(s.id) AS count')
            ->groupBy('s.channel')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): array => ['channel' => $row['channel'], 'count' => (int) $row['count']],
            $rows,
        );
    }
}
