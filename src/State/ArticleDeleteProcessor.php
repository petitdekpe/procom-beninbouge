<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Service\ArticleSaver;

/**
 * DELETE on the API moves the article to the trash rather than removing
 * the row outright — consistent with the backoffice (see
 * App\Controller\Admin\ArticleController::delete() and
 * App\Service\ArticleSaver::softDelete()). Permanent deletion only
 * happens from the trash view itself.
 *
 * @implements ProcessorInterface<Article, void>
 */
final class ArticleDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private ArticleSaver $articleSaver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Article) {
            $this->articleSaver->softDelete($data);
        }

        return null;
    }
}
