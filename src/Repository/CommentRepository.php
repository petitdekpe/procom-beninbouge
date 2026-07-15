<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Enum\CommentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Approved comments for a given article, oldest first — for the
     * public article page.
     *
     * @return list<Comment>
     */
    public function findApprovedByArticle(Article $article): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.article = :article')
            ->andWhere('c.status = :status')
            ->setParameter('article', $article)
            ->setParameter('status', CommentStatus::Approved)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comments awaiting moderation, most recent first — for the admin
     * moderation queue.
     *
     * @return list<Comment>
     */
    public function findByStatus(CommentStatus $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(CommentStatus $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, int> approved comment count keyed by article id,
     *                          only for articles with at least one
     */
    public function countApprovedByArticleMap(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.article) AS articleId, COUNT(c.id) AS commentCount')
            ->andWhere('c.status = :status')
            ->setParameter('status', CommentStatus::Approved)
            ->groupBy('c.article')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['articleId']] = (int) $row['commentCount'];
        }

        return $map;
    }
}
