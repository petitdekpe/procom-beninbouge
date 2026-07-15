<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Enum\ActivityAction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journalise les actions sensibles (publication, modération, suppression)
 * dans ActivityLog, en attribuant automatiquement l'utilisateur courant.
 */
final class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function log(ActivityAction $action, string $entityType, int $entityId, ?string $details = null): void
    {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDetails($details);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setActor($user);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
