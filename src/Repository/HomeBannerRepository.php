<?php

namespace App\Repository;

use App\Entity\HomeBanner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeBanner>
 */
class HomeBannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeBanner::class);
    }

    /**
     * @return list<HomeBanner>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Les publicités réellement affichées dans le slider de l'accueil :
     * actives et dans leur période de diffusion.
     *
     * @return list<HomeBanner>
     */
    public function findLiveOrdered(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('b')
            ->andWhere('b.enabled = true')
            ->andWhere('b.startsAt IS NOT NULL AND b.startsAt <= :now')
            ->andWhere('b.endsAt IS NOT NULL AND b.endsAt > :now')
            ->setParameter('now', $now)
            ->orderBy('b.position', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pour les onglets de l'admin : live | scheduled | expired | paused.
     *
     * @return list<HomeBanner>
     */
    public function findForAdmin(string $tab, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('b');

        // :now n'est lié que là où il sert (Doctrine refuse un paramètre inutilisé).
        match ($tab) {
            'scheduled' => $qb->andWhere('b.enabled = true AND b.startsAt > :now')->setParameter('now', $now)->orderBy('b.startsAt', 'ASC'),
            'expired' => $qb->andWhere('b.enabled = true AND b.endsAt <= :now')->setParameter('now', $now)->orderBy('b.endsAt', 'DESC'),
            'paused' => $qb->andWhere('b.enabled = false')->orderBy('b.id', 'DESC'),
            default => $qb->andWhere('b.enabled = true AND b.startsAt <= :now AND b.endsAt > :now')->setParameter('now', $now)->orderBy('b.position', 'ASC'),
        };

        return $qb->addOrderBy('b.id', 'ASC')->getQuery()->getResult();
    }

    /**
     * @return array{live: int, scheduled: int, expired: int, paused: int}
     */
    public function countByTab(?\DateTimeImmutable $now = null): array
    {
        $counts = ['live' => 0, 'scheduled' => 0, 'expired' => 0, 'paused' => 0];
        foreach ($this->findAll() as $banner) {
            ++$counts[$banner->getStatus($now)];
        }

        return $counts;
    }

    public function nextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('b')
            ->select('COALESCE(MAX(b.position), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
