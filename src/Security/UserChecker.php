<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion d'un compte désactivé depuis /admin/users, même avec
 * le bon mot de passe (branché sur le pare-feu dans security.yaml).
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez un administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
