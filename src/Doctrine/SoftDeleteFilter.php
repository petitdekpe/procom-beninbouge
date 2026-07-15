<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Appends "deleted_at IS NULL" to every query touching an entity that
 * implements SoftDeletableInterface. Registered globally and enabled by
 * default in config/packages/doctrine.yaml, so trashed rows disappear from
 * the public site, the admin, and the API without any individual query
 * needing to know about it.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
