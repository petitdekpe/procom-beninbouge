<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Comment;
use App\Entity\Enum\ActivityAction;
use App\Service\ActivityLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Comment, Comment>
 */
final class CommentModerateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ActivityLogger $activityLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($data instanceof Comment) {
            $this->activityLogger->log(
                ActivityAction::Moderation,
                'Comment',
                $data->getId(),
                sprintf('status=%s', $data->getStatus()->value),
            );
        }

        return $result;
    }
}
