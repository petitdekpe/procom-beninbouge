<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Enum\MediaType;
use App\Entity\Media;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Latest media of a given type, safe to show publicly: either attached
     * to a published article, or with no article at all (videos imported
     * automatically from the YouTube channel, which have no draft/publish
     * workflow of their own).
     *
     * @return list<Media>
     */
    public function findLatestPublishedByType(MediaType $type, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.article', 'a')
            ->andWhere('m.type = :type')
            ->andWhere('m.article IS NULL OR a.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest media tagged with a given rubrique, safe to show publicly —
     * same "standalone or published article" reasoning as
     * findLatestPublishedByType() — for the public rubrique page
     * (App\Controller\CategoryController).
     *
     * @return list<Media>
     */
    public function findLatestPublishedByCategory(Category $category, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.article', 'a')
            ->innerJoin('m.categories', 'c')
            ->andWhere('c = :category')
            ->andWhere('m.article IS NULL OR a.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest media tagged with a given mot-clé, safe to show publicly —
     * for the public tag page (App\Controller\TagController).
     *
     * @return list<Media>
     */
    public function findLatestPublishedByTag(Tag $tag, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.article', 'a')
            ->innerJoin('m.tags', 't')
            ->andWhere('t = :tag')
            ->andWhere('m.article IS NULL OR a.status = :status')
            ->setParameter('tag', $tag)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
