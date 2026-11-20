<?php

namespace App\Repository;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Recap;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recap>
 */
class RecapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recap::class);
    }

    /**
     * Latest published récaps, most recent first — feeds the homepage
     * "Récap" section and the "Autres récaps" rail of a récap page.
     *
     * @return list<Recap>
     */
    public function findLatestPublished(int $limit, ?Recap $except = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.videoUrl IS NOT NULL')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('r.publishedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $except) {
            $qb->andWhere('r.id != :except')->setParameter('except', $except->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->andWhere('r.videoUrl IS NOT NULL')
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * One page of published récaps, most recent first — for the public
     * "Récap" listing (App\Controller\RecapController::index).
     *
     * @return list<Recap>
     */
    public function findPublishedPage(int $page, int $perPage): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.videoUrl IS NOT NULL')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('r.publishedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }
}
