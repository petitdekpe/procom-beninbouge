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
    /**
     * Only videos imported from the YouTube channel (App\Service\YoutubeChannelSyncService,
     * which sets youtubeVideoId) are shown publicly — videos added by hand in
     * the admin are ignored. Other media types are unaffected.
     */
    private const YOUTUBE_ONLY_VIDEOS = 'm.type <> :video OR m.youtubeVideoId IS NOT NULL';

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
            ->andWhere(self::YOUTUBE_ONLY_VIDEOS)
            ->setParameter('video', MediaType::Video)
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
            ->andWhere(self::YOUTUBE_ONLY_VIDEOS)
            ->setParameter('video', MediaType::Video)
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
            ->andWhere(self::YOUTUBE_ONLY_VIDEOS)
            ->setParameter('video', MediaType::Video)
            ->setParameter('tag', $tag)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Number of videos shown publicly (YouTube channel imports only — see
     * YOUTUBE_ONLY_VIDEOS), for the À propos page key figures.
     */
    public function countPublicVideos(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.type = :video')
            ->andWhere('m.youtubeVideoId IS NOT NULL')
            ->setParameter('video', MediaType::Video)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * One page of the public videos, newest first, for the paginated /videos
     * page (App\Controller\VideoController). Total count: countPublicVideos().
     *
     * @return list<Media>
     */
    public function findPublicVideosPage(int $page, int $perPage): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :video')
            ->andWhere('m.youtubeVideoId IS NOT NULL')
            ->setParameter('video', MediaType::Video)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }
}
