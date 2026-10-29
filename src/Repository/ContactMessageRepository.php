<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * @return list<ContactMessage> newest first; unread only when $unreadOnly
     */
    public function findForAdmin(bool $unreadOnly): array
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.createdAt', 'DESC');

        if ($unreadOnly) {
            $qb->andWhere('m.isRead = false');
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnread(): int
    {
        return $this->count(['isRead' => false]);
    }
}
