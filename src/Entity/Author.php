<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\AuthorRepository;
use App\State\AuthorWriteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_EDITOR')"),
        new Put(security: "is_granted('ROLE_EDITOR')", processor: AuthorWriteProcessor::class),
        new Patch(security: "is_granted('ROLE_EDITOR')", processor: AuthorWriteProcessor::class),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
)]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    /**
     * Links this Author "byline" to the editor's own account, when this
     * profile represents a real ROLE_EDITOR user rather than a guest
     * contributor. Direct edits to a linked profile are blocked outside
     * the validation workflow (see AuthorWriteProcessor and
     * App\Controller\Admin\ProfileController) — only the pending* fields
     * below may be changed freely, by the owner, pending admin approval.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'author')]
    #[ORM\JoinColumn(nullable: true, unique: true)]
    private ?User $user = null;

    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pendingFirstName = null;

    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pendingLastName = null;

    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pendingEmail = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
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

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPendingFirstName(): ?string
    {
        return $this->pendingFirstName;
    }

    public function setPendingFirstName(?string $pendingFirstName): static
    {
        $this->pendingFirstName = $pendingFirstName;

        return $this;
    }

    public function getPendingLastName(): ?string
    {
        return $this->pendingLastName;
    }

    public function setPendingLastName(?string $pendingLastName): static
    {
        $this->pendingLastName = $pendingLastName;

        return $this;
    }

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): static
    {
        $this->pendingEmail = $pendingEmail;

        return $this;
    }

    #[ApiProperty(readable: false, writable: false)]
    public function hasPendingChanges(): bool
    {
        return null !== $this->pendingFirstName || null !== $this->pendingLastName || null !== $this->pendingEmail;
    }

    /**
     * Applies the pending profile-change request (admin approval) and
     * clears it.
     */
    public function approvePendingChanges(): static
    {
        if (null !== $this->pendingFirstName) {
            $this->firstName = $this->pendingFirstName;
        }
        if (null !== $this->pendingLastName) {
            $this->lastName = $this->pendingLastName;
        }
        if (null !== $this->pendingEmail) {
            $this->email = $this->pendingEmail;
        }

        return $this->clearPendingChanges();
    }

    public function clearPendingChanges(): static
    {
        $this->pendingFirstName = null;
        $this->pendingLastName = null;
        $this->pendingEmail = null;

        return $this;
    }
}
