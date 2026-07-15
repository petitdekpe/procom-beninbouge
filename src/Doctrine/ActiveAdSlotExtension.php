<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\AdSlot;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts public read operations (GET collection/item) on AdSlot to
 * slots that are active and currently within their display period.
 * Write operations are left untouched so editors/admins can still manage
 * inactive or future/expired slots through Post/Put/Patch/Delete.
 */
final class ActiveAdSlotExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof GetCollection) {
            return;
        }

        $this->restrictToActive($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof Get) {
            return;
        }

        $this->restrictToActive($queryBuilder, $resourceClass);
    }

    private function restrictToActive(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (AdSlot::class !== $resourceClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('%s.active = :ad_slot_active', $alias))
            ->andWhere(sprintf('%s.startAt <= :ad_slot_now', $alias))
            ->andWhere($queryBuilder->expr()->orX(
                sprintf('%s.endAt IS NULL', $alias),
                sprintf('%s.endAt >= :ad_slot_now', $alias),
            ))
            ->setParameter('ad_slot_active', true)
            ->setParameter('ad_slot_now', new \DateTimeImmutable());
    }
}
