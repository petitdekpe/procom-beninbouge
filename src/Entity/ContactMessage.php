<?php

namespace App\Entity;

use App\Entity\Enum\ContactSubject;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Message laissé par un visiteur via la section « Nous joindre » de
 * l'accueil (App\Controller\ContactController). Anonyme comme les
 * commentaires : pas de compte lecteur. Consultable uniquement dans l'admin
 * (App\Controller\Admin\ContactMessageController) — volontairement absent
 * de l'API, les coordonnées étant des données personnelles.
 */
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom complet est requis.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom complet doit contenir au moins 2 caractères.', maxMessage: 'Le nom complet ne peut pas dépasser 100 caractères.')]
    private ?string $fullName = null;

    /**
     * Numéro complet, indicatif compris (ex. « +22901XXXXXXXX »).
     */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est requis.')]
    #[Assert\Regex(pattern: '/^\+\d{1,4}\d{8,15}$/', message: 'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.')]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: "L'adresse e-mail est invalide.")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 20, enumType: ContactSubject::class)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un objet.')]
    private ?ContactSubject $subject = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le message est requis.')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'Le message doit contenir au moins 10 caractères.', maxMessage: 'Le message ne peut pas dépasser 1000 caractères.')]
    private ?string $message = null;

    #[ORM\Column]
    private bool $isRead = false;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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

    public function getSubject(): ?ContactSubject
    {
        return $this->subject;
    }

    public function setSubject(?ContactSubject $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
