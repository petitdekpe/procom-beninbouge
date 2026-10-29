<?php

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use Doctrine\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adresse inscrite à la newsletter depuis le formulaire du footer
 * (App\Controller\ContactController::subscribe). Liste consultable et
 * exportable en CSV dans l'admin — l'envoi des newsletters se fait en
 * dehors du site.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse est déjà inscrite à la newsletter.')]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'adresse e-mail est requise.")]
    #[Assert\Email(message: "L'adresse e-mail est invalide.")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = null !== $email ? mb_strtolower(trim($email)) : null;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
