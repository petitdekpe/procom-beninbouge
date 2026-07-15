<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Article;
use Doctrine\ORM\QueryBuilder;

/**
 * Free-text search ("q" query parameter) across an article's title,
 * content and tag names, combined with OR conditions.
 */
final class ArticleSearchFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Article::class !== $resourceClass) {
            return;
        }

        $value = $context['filters']['q'] ?? null;
        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $tagsAlias = $queryNameGenerator->generateJoinAlias('tags');

        $queryBuilder
            ->leftJoin(sprintf('%s.tags', $alias), $tagsAlias)
            ->andWhere($queryBuilder->expr()->orX(
                sprintf('%s.title LIKE :search_q', $alias),
                sprintf('%s.content LIKE :search_q', $alias),
                sprintf('%s.name LIKE :search_q', $tagsAlias),
            ))
            ->setParameter('search_q', '%'.$value.'%')
            ->distinct();
    }

    public function getDescription(string $resourceClass): array
    {
        if (Article::class !== $resourceClass) {
            return [];
        }

        return [
            'q' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Recherche plein texte sur le titre, le contenu et les tags.',
            ],
        ];
    }
}
