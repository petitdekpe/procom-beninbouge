<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use App\State\UserRegisterProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every registered user is at least ROLE_READER (can comment, react,
 * vote). ROLE_EDITOR (journaliste), ROLE_EDITOR_IN_CHIEF (rédacteur en
 * chef), ROLE_PROOFREADER (correcteur), ROLE_ADMIN and any custom role
 * (App\Entity\Role) are granted explicitly by an admin, from /admin/users
 * or through the dedicated /users/{id}/roles endpoint. What a role may do
 * in the back-office is defined by its permissions (App\Security\Permission).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Post(
            processor: UserRegisterProcessor::class,
            validationContext: ['groups' => ['Default', 'user:write']],
        ),
        new Patch(
            uriTemplate: '/users/{id}/roles',
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['user:manage_roles']],
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $displayName = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    // Les rôles viennent de la table app_role (App\Entity\Role), y compris les
    // rôles personnalisés : on contrôle seulement la forme du code.
    #[Assert\All([new Assert\Regex(pattern: '/^ROLE_[A-Z0-9_]+$/')])]
    #[Groups(['user:read', 'user:manage_roles'])]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[Assert\NotBlank(groups: ['user:write'])]
    #[Assert\Length(min: 8, groups: ['user:write'])]
    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Compte désactivé depuis /admin/users : connexion refusée
     * (App\Security\UserChecker), sans supprimer l'historique du compte.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    /**
     * Vrai tant que le mot de passe est un mot de passe temporaire envoyé par
     * e-mail : l'administration redirige alors vers le changement de mot de
     * passe (App\EventSubscriber\PasswordChangeSubscriber).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $mustChangePassword = false;

    /**
     * The editor's own "byline" identity (see Author::$user). Most users
     * never have one; it is created the first time ROLE_EDITOR is granted.
     */
    #[Groups(['user:read'])]
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Author::class)]
    private ?Author $author = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_READER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }
}
