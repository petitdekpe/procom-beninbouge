<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use Doctrine\ORM\QueryBuilder;

/**
 * Restricts public read operations (GET collection/item) on Article to
 * status=published only. Write operations (POST/PUT/PATCH/DELETE) are
 * left untouched so that any future back-office can still manage drafts
 * and scheduled articles through the same API.
 */
final class PublishedArticleExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof GetCollection) {
            return;
        }

        $this->restrictToPublished($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if (!$operation instanceof Get) {
            return;
        }

        $this->restrictToPublished($queryBuilder, $resourceClass);
    }

    private function restrictToPublished(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Article::class !== $resourceClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('%s.status = :published_status', $alias))
            ->setParameter('published_status', ArticleStatus::Published);
    }
}
