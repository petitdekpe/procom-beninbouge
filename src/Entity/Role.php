<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use App\Security\Permission;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un rôle de l'administration : un nom et un ensemble de permissions
 * (App\Security\Permission). Les utilisateurs portent le code du rôle
 * (« ROLE_… ») dans User::$roles ; les permissions se lisent ici, au moment
 * du contrôle (App\Security\PermissionChecker).
 *
 * Les rôles « système » (Journaliste, Rédacteur en chef, Correcteur,
 * Administrateur) existent dès l'installation : leur code ne change pas et
 * ils ne se suppriment pas, car le reste du site s'appuie dessus. Leurs
 * permissions se règlent, sauf celles de ROLE_ADMIN (accès total).
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'app_role', options: ['engine' => 'InnoDB'])]
#[UniqueEntity(fields: ['code'], message: 'Un rôle avec ce nom existe déjà.')]
class Role
{
    public const ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le nom du rôle est requis.')]
    #[Assert\Length(max: 80)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    /**
     * @var list<string> valeurs de App\Security\Permission
     */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    // « system » est un mot réservé de MySQL : la colonne s'appelle is_system.
    #[ORM\Column(name: 'is_system')]
    private bool $system = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    /**
     * ROLE_ADMIN a toutes les permissions, quoi qu'il soit enregistré.
     *
     * @return list<string>
     */
    public function getPermissions(): array
    {
        if ($this->isAdmin()) {
            return Permission::values();
        }

        return array_values(array_intersect(Permission::values(), $this->permissions));
    }

    /**
     * @param list<string> $permissions
     */
    public function setPermissions(array $permissions): static
    {
        $this->permissions = array_values(array_intersect(Permission::values(), $permissions));

        return $this;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return \in_array($value, $this->getPermissions(), true);
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function setSystem(bool $system): static
    {
        $this->system = $system;

        return $this;
    }

    public function isAdmin(): bool
    {
        return self::ADMIN === $this->code;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
