<?php

namespace App\Doctrine;

/**
 * Marks an entity as subject to soft-delete (see SoftDeleteFilter): rows
 * with deletedAt set are transparently excluded from every ORM query
 * (find, repository methods, API Platform) unless the "soft_delete"
 * Doctrine filter is explicitly disabled — see
 * ArticleRepository::findTrashed() for how the trash listing does that.
 */
interface SoftDeletableInterface
{
}
