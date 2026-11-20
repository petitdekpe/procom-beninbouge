<?php

namespace App\Entity;

use App\Repository\HomeBannerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Une publicité du slider de l'accueil : une image rectangulaire affichée
 * au-dessus de l'actualité (section « Bannières »). Elle se gère comme une
 * annonce (App\Entity\Announcement) : référence (PUB-…), annonceur et
 * contact, durée en jours à compter de la mise en ligne, montant facturé,
 * mise en pause. Gérée dans /admin/bannieres par la rédaction (pas de
 * dépôt public ni de paiement en ligne : l'annonceur est facturé à part).
 *
 * Diffusée quand elle est active (enabled) et dans sa période
 * (startsAt ≤ maintenant < endsAt). Image requise, contrôlée par le
 * contrôleur d'admin plutôt que par une contrainte : un fichier téléversé ne
 * donne son URL qu'après la validation du formulaire.
 */
#[ORM\Entity(repositoryClass: HomeBannerRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[Assert\Callback(callback: 'validateLink')]
class HomeBanner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom de la bannière dans l'admin et texte alternatif de l'image.
     */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private ?string $title = null;

    /** Référence lisible (ex. « PUB-7K3Q9D »), comme celle des annonces. */
    #[ORM\Column(length: 12, unique: true)]
    private string $reference;

    /** Annonceur : l'entreprise ou l'organisation qui paie la publicité. */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: "Le nom de l'annonceur est requis.")]
    #[Assert\Length(max: 120)]
    private ?string $advertiser = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $contactName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: "L'adresse e-mail est invalide.")]
    #[Assert\Length(max: 180)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^\+?[0-9 ]{8,20}$/', message: 'Le numéro de téléphone est invalide.')]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageUrl = null;

    /**
     * Où mène un clic sur l'image : lien complet (https://…) ou chemin du
     * site (/article/mon-article). Vide : l'image n'est pas cliquable.
     */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $linkUrl = null;

    /** Montant facturé à l'annonceur (FCFA), à titre de suivi. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $amount = 0;

    #[ORM\Column]
    #[Assert\Positive(message: 'La durée doit être d\'au moins 1 jour.')]
    #[Assert\LessThanOrEqual(value: 730, message: 'La durée ne peut pas dépasser 730 jours.')]
    private int $durationDays = 30;

    /** Début de la diffusion. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    /** Fin de la diffusion : startsAt + durationDays. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->startsAt = new \DateTimeImmutable();
        $this->endsAt = $this->startsAt->modify('+30 days');
        // Sans 0/O ni 1/I pour pouvoir être dictée sans ambiguïté.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; ++$i) {
            $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }
        $this->reference = 'PUB-'.$code;
    }

    /**
     * Statut de diffusion : « live » (en ligne), « scheduled » (programmée),
     * « expired » (terminée) ou « paused » (mise en pause par la rédaction).
     */
    public function getStatus(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();

        if (!$this->enabled) {
            return 'paused';
        }
        if (null !== $this->startsAt && $this->startsAt > $now) {
            return 'scheduled';
        }
        if (null !== $this->endsAt && $this->endsAt <= $now) {
            return 'expired';
        }

        return 'live';
    }

    public function isLive(?\DateTimeImmutable $now = null): bool
    {
        return 'live' === $this->getStatus($now);
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            'live' => 'En ligne',
            'scheduled' => 'Programmée',
            'expired' => 'Terminée',
            default => 'En pause',
        };
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAdvertiser(): ?string
    {
        return $this->advertiser;
    }

    public function setAdvertiser(?string $advertiser): static
    {
        $this->advertiser = $advertiser;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    /**
     * La fin de diffusion suit toujours le début et la durée : allonger une
     * publicité terminée la remet en ligne.
     */
    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;
        $this->syncEndsAt();

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;
        $this->syncEndsAt();

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    private function syncEndsAt(): void
    {
        $this->endsAt = null === $this->startsAt ? null : $this->startsAt->modify(sprintf('+%d days', max(1, $this->durationDays)));
    }

    public function validateLink(ExecutionContextInterface $context): void
    {
        if ($this->linkUrl && 1 !== preg_match('#^(?:https?://[^\s]+|/(?!/)[^\s]*)$#i', $this->linkUrl)) {
            $context->buildViolation('Le lien doit commencer par https:// (ou http://), ou par / pour une page du site.')
                ->atPath('linkUrl')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $imageUrl = null === $imageUrl ? null : trim($imageUrl);
        $this->imageUrl = '' === $imageUrl ? null : $imageUrl;

        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): static
    {
        $linkUrl = null === $linkUrl ? null : trim($linkUrl);
        $this->linkUrl = '' === $linkUrl ? null : $linkUrl;

        return $this;
    }

    /**
     * Lien vers un autre site : s'ouvre dans un nouvel onglet.
     */
    public function isExternalLink(): bool
    {
        return null !== $this->linkUrl && str_starts_with(strtolower($this->linkUrl), 'http');
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
