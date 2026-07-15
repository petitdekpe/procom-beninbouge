<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * @return list<Author>
     */
    public function findPendingProfileChanges(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.pendingFirstName IS NOT NULL')
            ->orWhere('a.pendingLastName IS NOT NULL')
            ->orWhere('a.pendingEmail IS NOT NULL')
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
