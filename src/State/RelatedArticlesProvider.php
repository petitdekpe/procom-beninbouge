<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ArticleRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<\App\Entity\Article>
 */
final class RelatedArticlesProvider implements ProviderInterface
{
    public function __construct(private ArticleRepository $articleRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $id = $uriVariables['id'] ?? null;
        $article = null !== $id ? $this->articleRepository->find($id) : null;

        if (null === $article) {
            throw new NotFoundHttpException('Article introuvable.');
        }

        return $this->articleRepository->findRelated($article);
    }
}
