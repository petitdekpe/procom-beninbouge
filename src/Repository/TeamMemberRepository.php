<?php

namespace App\Repository;

use App\Entity\TeamMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    /**
     * @return list<TeamMember> every member in display order (admin list)
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return list<TeamMember> visible members in display order (À propos page)
     */
    public function findVisibleOrdered(): array
    {
        return $this->findBy(['visible' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function nextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('t')
            ->select('COALESCE(MAX(t.position), -1)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
