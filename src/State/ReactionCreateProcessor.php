<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Reaction;
use App\Entity\User;
use App\Repository\ReactionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<Reaction, Reaction>
 */
final class ReactionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private ReactionRepository $reactionRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Reaction) {
            $user = $this->security->getUser();

            if ($user instanceof User) {
                $data->setAuthor($user);

                $existing = $this->reactionRepository->findOneBy([
                    'article' => $data->getArticle(),
                    'author' => $user,
                ]);

                if (null !== $existing) {
                    throw new ConflictHttpException('Vous avez déjà réagi à cet article.');
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
