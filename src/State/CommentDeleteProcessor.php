<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Comment;
use App\Entity\Enum\ActivityAction;
use App\Service\ActivityLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Comment, void>
 */
final class CommentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private ActivityLogger $activityLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $data instanceof Comment ? $data->getId() : null;

        $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);

        if (null !== $id) {
            $this->activityLogger->log(ActivityAction::Deletion, 'Comment', $id);
        }

        return $result;
    }
}
