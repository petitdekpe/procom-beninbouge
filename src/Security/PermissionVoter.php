<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Répond aux attributs « permission » : #[IsGranted('recaps.manage')],
 * is_granted('stats.view'), ou « admin.access » (au moins une permission).
 * Les attributs « ROLE_… » restent traités par le vote de rôles standard.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    public function __construct(
        private PermissionChecker $checker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::ADMIN_ACCESS === $attribute || null !== Permission::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->checker->has($user, $attribute);
    }
}
