<?php

namespace App\Repository;

use App\Entity\MobileToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MobileToken>
 */
class MobileTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileToken::class);
    }

    public function findOneByPlainToken(string $plainToken): ?MobileToken
    {
        return $this->findOneBy(['tokenHash' => MobileToken::hash($plainToken)]);
    }

    /**
     * @return list<MobileToken>
     */
    public function findOfUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['lastUsedAt' => 'DESC']);
    }

    /**
     * @return list<MobileToken> les téléphones qui acceptent les notifications
     */
    public function findWithPushToken(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.pushToken IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
