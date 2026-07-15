<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Author;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * An Author linked to a real ROLE_EDITOR account (Author::$user) is that
 * editor's own byline identity: it may only change through the
 * profile-change-request workflow (App\Controller\Admin\ProfileController
 * + admin approval in ValidationController), never through a direct
 * PUT/PATCH — otherwise an editor could edit their own name/email straight
 * past the validation the feature exists to enforce.
 *
 * @implements ProcessorInterface<Author, Author>
 */
final class AuthorWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Author && $data->getUser() instanceof User && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Le profil d\'un éditeur ne peut être modifié que via une demande de validation (page « Mon profil »).');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
