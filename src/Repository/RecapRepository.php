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
}
