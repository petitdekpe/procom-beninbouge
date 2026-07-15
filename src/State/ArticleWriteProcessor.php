<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Service\ArticleSaver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fills in SEO metadata (slug, meta title, meta description) for an
 * Article whenever the client leaves them blank, then invalidates the
 * Redis-backed listing cache and logs publication events. The actual
 * side-effect logic lives in App\Service\ArticleSaver, shared with the
 * backoffice controller.
 *
 * @implements ProcessorInterface<Article, Article>
 */
final class ArticleWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ArticleSaver $articleSaver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $wasPublished = false;

        if ($data instanceof Article) {
            $wasPublished = $this->articleSaver->applyDefaults($data);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($data instanceof Article) {
            $this->articleSaver->afterSave($data, $wasPublished);
        }

        return $result;
    }
}
