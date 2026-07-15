<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PollVote;
use App\Entity\User;
use App\Repository\PollVoteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<PollVote, PollVote>
 */
final class PollVoteCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private PollVoteRepository $pollVoteRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof PollVote) {
            $option = $data->getOption();
            $poll = $data->getPoll();

            if (null === $option || null === $poll || $option->getPoll() !== $poll) {
                throw new BadRequestHttpException("L'option choisie n'appartient pas au sondage indiqué.");
            }

            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setVoter($user);

                $existing = $this->pollVoteRepository->findOneBy(['poll' => $poll, 'voter' => $user]);
                if (null !== $existing) {
                    throw new ConflictHttpException('Vous avez déjà voté pour ce sondage.');
                }
            }

            $option->incrementVotesCount();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
