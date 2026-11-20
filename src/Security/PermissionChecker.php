<?php

namespace App\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Calcule les permissions d'un utilisateur : l'union des permissions des
 * rôles qu'il porte (App\Entity\Role). ROLE_ADMIN a tout. Les rôles sont
 * lus une seule fois par requête.
 */
final class PermissionChecker implements ResetInterface
{
    /** @var array<string, list<string>>|null code du rôle => permissions */
    private ?array $rolePermissions = null;

    public function __construct(
        private RoleRepository $roleRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function permissionsOf(User $user): array
    {
        return $this->permissionsForRoles($user->getRoles());
    }

    /**
     * @param list<string> $roleCodes
     *
     * @return list<string>
     */
    public function permissionsForRoles(array $roleCodes): array
    {
        if (\in_array(Role::ADMIN, $roleCodes, true)) {
            return Permission::values();
        }

        $map = $this->map();
        $permissions = [];
        foreach ($roleCodes as $code) {
            foreach ($map[$code] ?? [] as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    public function has(User $user, string $permission): bool
    {
        $permissions = $this->permissionsOf($user);

        if (Permission::ADMIN_ACCESS === $permission) {
            return [] !== $permissions;
        }

        return \in_array($permission, $permissions, true);
    }

    /**
     * @param list<string> $roleCodes
     */
    public function rolesGrant(array $roleCodes, Permission $permission): bool
    {
        return \in_array($permission->value, $this->permissionsForRoles($roleCodes), true);
    }

    public function reset(): void
    {
        $this->rolePermissions = null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function map(): array
    {
        if (null === $this->rolePermissions) {
            $this->rolePermissions = [];
            foreach ($this->roleRepository->findAll() as $role) {
                $this->rolePermissions[(string) $role->getCode()] = $role->getPermissions();
            }
        }

        return $this->rolePermissions;
    }
}
