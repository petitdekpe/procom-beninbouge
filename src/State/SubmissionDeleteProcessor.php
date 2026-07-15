<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Enum\ActivityAction;
use App\Entity\Submission;
use App\Service\ActivityLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Submission, void>
 */
final class SubmissionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private ActivityLogger $activityLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $data instanceof Submission ? $data->getId() : null;

        $result = $this->removeProcessor->process($data, $operation, $uriVariables, $context);

        if (null !== $id) {
            $this->activityLogger->log(ActivityAction::Deletion, 'Submission', $id);
        }

        return $result;
    }
}
