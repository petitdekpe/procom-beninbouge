<?php

namespace App\Service\Mobile;

use App\Entity\MobileRequestKey;
use App\Entity\User;
use App\Repository\MobileRequestKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * Évite les doublons quand l'application renvoie une création après une
 * coupure réseau (voir App\Entity\MobileRequestKey).
 */
final class RequestKeys
{
    public function __construct(
        private MobileRequestKeyRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * L'objet déjà créé par cette même demande, ou null s'il faut le créer.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function existing(User $user, string $clientId, string $type, string $class): ?object
    {
        if (1 !== preg_match('/^[A-Za-z0-9-]{8,64}$/', $clientId)) {
            throw new BadRequestHttpException('Identifiant de demande (clientId) absent ou invalide.');
        }

        $key = $this->repository->findOneBy(['user' => $user, 'clientId' => $clientId]);
        if (null === $key) {
            return null;
        }
        if ($key->getTargetType() !== $type) {
            throw new BadRequestHttpException('Identifiant de demande déjà utilisé.');
        }

        // findOneBy applique le filtre de corbeille, contrairement à find().
        return $this->entityManager->getRepository($class)->findOneBy(['id' => $key->getTargetId()])
            ?? throw new GoneHttpException('Cet élément a été supprimé depuis son envoi.');
    }

    public function remember(User $user, string $clientId, string $type, int $targetId): void
    {
        $this->entityManager->persist(new MobileRequestKey($user, $clientId, $type, $targetId));
        $this->entityManager->flush();
    }
}
