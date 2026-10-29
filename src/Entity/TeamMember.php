<?php

namespace App\Entity;

use App\Repository\TeamMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Membre de l'équipe affiché dans le bloc « Notre équipe » de la page
 * À propos (App\Controller\AboutController), saisi dans le back-office
 * (App\Controller\Admin\TeamMemberController). Indépendant d'Author :
 * l'équipe peut compter des personnes qui ne signent pas d'articles
 * (direction, technique, partenariats…).
 */
#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
class TeamMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est requis.')]
    #[Assert\Length(max: 100)]
    private ?string $fullName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La fonction est requise.')]
    #[Assert\Length(max: 100)]
    private ?string $role = null;

    /**
     * URL de la photo : téléversée via le formulaire admin
     * (App\Service\ImageUploader) ou saisie à la main.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^(https?://|/)\S+$#', message: 'La photo doit être une adresse web (https://…) ou un fichier du site (/uploads/…).')]
    private ?string $photo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $bio = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: "L'adresse e-mail est invalide.")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "L'adresse LinkedIn doit être une URL valide.", requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $linkedinUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "L'adresse X / Twitter doit être une URL valide.", requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $twitterUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "L'adresse Facebook doit être une URL valide.", requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $facebookUrl = null;

    /**
     * Ordre d'affichage sur la page À propos (croissant), modifié avec les
     * flèches ↑/↓ de la liste admin.
     */
    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $visible = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getLinkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }

    public function setLinkedinUrl(?string $linkedinUrl): static
    {
        $this->linkedinUrl = $linkedinUrl;

        return $this;
    }

    public function getTwitterUrl(): ?string
    {
        return $this->twitterUrl;
    }

    public function setTwitterUrl(?string $twitterUrl): static
    {
        $this->twitterUrl = $twitterUrl;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): static
    {
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Initiales affichées quand il n'y a pas de photo (« Awa Houngbo » → « AH »).
     */
    public function getInitials(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->fullName), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = mb_substr($words[0] ?? '', 0, 1).(\count($words) > 1 ? mb_substr(end($words), 0, 1) : '');

        return mb_strtoupper($initials);
    }
}
