<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Comment;
use App\Entity\Enum\CommentStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Comment, Comment>
 */
final class CommentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Comment) {
            // A comment always starts pending moderation, regardless of
            // what the client may have submitted for this field.
            $data->setStatus(CommentStatus::Pending);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
