<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Comment;
use App\Entity\Enum\CommentStatus;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts public read operations (GET collection/item) on Comment to
 * status=approved only. Write operations are left untouched: they are
 * already protected by authentication/authorization at the operation level.
 */
final class ApprovedCommentExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof GetCollection) {
            return;
        }

        $this->restrictToApproved($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof Get) {
            return;
        }

        $this->restrictToApproved($queryBuilder, $resourceClass);
    }

    private function restrictToApproved(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Comment::class !== $resourceClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('%s.status = :approved_status', $alias))
            ->setParameter('approved_status', CommentStatus::Approved);
    }
}
