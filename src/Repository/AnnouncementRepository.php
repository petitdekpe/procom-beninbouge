<?php

namespace App\Repository;

use App\Entity\Announcement;
use App\Entity\Enum\AnnouncementCategory;
use App\Entity\Enum\AnnouncementStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * @return list<Announcement> one page of online announcements, newest first
     */
    public function findOnlinePage(?AnnouncementCategory $category, int $page, int $perPage): array
    {
        return $this->online($category)
            ->orderBy('a.startsAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countOnline(?AnnouncementCategory $category = null): int
    {
        return (int) $this->online($category)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> online announcement count per category value
     */
    public function countOnlineByCategory(): array
    {
        $rows = $this->online(null)
            ->select('a.category AS category, COUNT(a.id) AS total')
            ->groupBy('a.category')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $category = $row['category'] instanceof AnnouncementCategory ? $row['category']->value : (string) $row['category'];
            $counts[$category] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return list<Announcement> online announcements that bought the homepage card, newest first
     */
    public function findOnlineForHomeCard(int $limit): array
    {
        return $this->online(null)
            ->andWhere('a.withHomeCard = true')
            ->orderBy('a.startsAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * One online announcement that bought the homepage pop-up, picked at
     * random so that several paying advertisers share the pop-up fairly.
     */
    public function findRandomOnlinePopup(): ?Announcement
    {
        $ids = $this->online(null)
            ->select('a.id')
            ->andWhere('a.withPopup = true')
            ->getQuery()
            ->getSingleColumnResult();

        return [] === $ids ? null : $this->find($ids[array_rand($ids)]);
    }

    public function findOnlineOneByReference(string $reference): ?Announcement
    {
        return $this->online(null)
            ->andWhere('a.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Admin tabs. "online" and "expired" are both Approved, split on endsAt.
     *
     * @return list<Announcement>
     */
    public function findForAdmin(string $tab): array
    {
        $now = new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('a');

        match ($tab) {
            'online' => $qb->andWhere('a.status = :approved AND a.endsAt > :now')
                ->setParameter('approved', AnnouncementStatus::Approved)->setParameter('now', $now)
                ->orderBy('a.endsAt', 'ASC'),
            'expired' => $qb->andWhere('a.status = :approved AND a.endsAt <= :now')
                ->setParameter('approved', AnnouncementStatus::Approved)->setParameter('now', $now)
                ->orderBy('a.endsAt', 'DESC'),
            'rejected' => $qb->andWhere('a.status = :status')->setParameter('status', AnnouncementStatus::Rejected)
                ->orderBy('a.reviewedAt', 'DESC'),
            'unpaid' => $qb->andWhere('a.status IN (:statuses)')
                ->setParameter('statuses', [AnnouncementStatus::PendingPayment, AnnouncementStatus::PaymentFailed])
                ->orderBy('a.createdAt', 'DESC'),
            // À valider : les plus anciennes d'abord (échéance de 48 h la plus proche).
            default => $qb->andWhere('a.status = :status')->setParameter('status', AnnouncementStatus::PendingReview)
                ->orderBy('a.paidAt', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function countPendingReview(): int
    {
        return $this->count(['status' => AnnouncementStatus::PendingReview]);
    }

    private function online(?AnnouncementCategory $category): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.status = :approved')
            ->andWhere('a.startsAt <= :now AND a.endsAt > :now')
            ->setParameter('approved', AnnouncementStatus::Approved)
            ->setParameter('now', new \DateTimeImmutable());

        if (null !== $category) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category);
        }

        return $qb;
    }
}
