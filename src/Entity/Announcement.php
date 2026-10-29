<?php

namespace App\Entity;

use App\Entity\Enum\AnnouncementCategory;
use App\Entity\Enum\AnnouncementStatus;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Annonce ou opportunité payante (page « Annonces & Opportunités »).
 *
 * Parcours : dépôt via le formulaire public (App\Controller\AnnouncementController::submit)
 * → paiement FedaPay → validation par la rédaction en 48 h au plus
 * (App\Controller\Admin\AnnouncementController) → mise en ligne pour
 * durationDays jours à compter de la validation, pour que le temps de
 * relecture ne soit pas décompté de la durée payée.
 *
 * Toujours visible sur la page Annonces une fois en ligne ; en option, une
 * carte sur l'accueil (withHomeCard) et un pop-up sur l'accueil (withPopup).
 * Le prix est calculé côté serveur (App\Service\AnnouncementPricing) et figé
 * dans amount au moment du dépôt.
 *
 * Pas de compte annonceur : trackingToken sert de lien secret de suivi.
 * Volontairement absent de l'API (coordonnées personnelles, paiement).
 */
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\Index(columns: ['status', 'ends_at'], name: 'idx_announcement_status_ends')]
class Announcement
{
    /** Délai de validation promis à l'annonceur. */
    public const REVIEW_DELAY_HOURS = 48;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence lisible communiquée à l'annonceur (ex. « ANN-7K3Q9D »). */
    #[ORM\Column(length: 12, unique: true)]
    private string $reference;

    /** Jeton secret du lien de suivi /annonces/suivi/{token}. */
    #[ORM\Column(length: 64, unique: true)]
    private string $trackingToken;

    #[ORM\Column(length: 30, enumType: AnnouncementCategory::class)]
    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    private ?AnnouncementCategory $category = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le titre est requis.')]
    #[Assert\Length(min: 5, max: 120, minMessage: 'Le titre doit contenir au moins 5 caractères.', maxMessage: 'Le titre ne peut pas dépasser 120 caractères.')]
    private ?string $title = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: "Le nom de l'organisation ou de l'annonceur est requis.")]
    #[Assert\Length(max: 120)]
    private ?string $organization = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est requise.')]
    #[Assert\Length(min: 30, max: 3000, minMessage: 'La description doit contenir au moins 30 caractères.', maxMessage: 'La description ne peut pas dépasser 3000 caractères.')]
    private ?string $description = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $location = null;

    /** Date limite de candidature / de l'événement, affichée à titre indicatif. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien doit être une adresse web valide.', requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $externalUrl = null;

    /** Image téléversée (App\Service\ImageUploader). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du contact est requis.')]
    #[Assert\Length(max: 100)]
    private ?string $contactName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'e-mail du contact est requis.")]
    #[Assert\Email(message: "L'adresse e-mail est invalide.")]
    #[Assert\Length(max: 180)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le téléphone du contact est requis.')]
    #[Assert\Regex(pattern: '/^\+?[0-9 ]{8,20}$/', message: 'Le numéro de téléphone est invalide.')]
    private ?string $contactPhone = null;

    /** Afficher les coordonnées du contact sur l'annonce publiée. */
    #[ORM\Column]
    private bool $showContact = true;

    #[ORM\Column]
    #[Assert\Positive]
    private int $durationDays = 3;

    #[ORM\Column]
    private bool $withHomeCard = false;

    #[ORM\Column]
    private bool $withPopup = false;

    /** Montant payé (FCFA), figé au dépôt. */
    #[ORM\Column]
    private int $amount = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'XOF';

    #[ORM\Column(length: 20, enumType: AnnouncementStatus::class)]
    private AnnouncementStatus $status = AnnouncementStatus::PendingPayment;

    /** Identifiant de la transaction FedaPay. */
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $transactionReference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->trackingToken = bin2hex(random_bytes(24));
        // Sans 0/O ni 1/I pour pouvoir être dictée sans ambiguïté.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; ++$i) {
            $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }
        $this->reference = 'ANN-'.$code;
    }

    public function isOnline(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return AnnouncementStatus::Approved === $this->status
            && null !== $this->startsAt && $this->startsAt <= $now
            && null !== $this->endsAt && $this->endsAt > $now;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return AnnouncementStatus::Approved === $this->status
            && null !== $this->endsAt && $this->endsAt <= ($now ?? new \DateTimeImmutable());
    }

    /** Échéance de validation promise (paiement + 48 h). */
    public function getReviewDeadline(): ?\DateTimeImmutable
    {
        return $this->paidAt?->modify(sprintf('+%d hours', self::REVIEW_DELAY_HOURS));
    }

    public function isReviewOverdue(?\DateTimeImmutable $now = null): bool
    {
        return AnnouncementStatus::PendingReview === $this->status
            && null !== $this->getReviewDeadline()
            && $this->getReviewDeadline() < ($now ?? new \DateTimeImmutable());
    }

    public function markPaid(\DateTimeImmutable $at): void
    {
        if (AnnouncementStatus::PendingPayment === $this->status || AnnouncementStatus::PaymentFailed === $this->status) {
            $this->status = AnnouncementStatus::PendingReview;
            $this->paidAt = $at;
        }
    }

    public function markPaymentFailed(): void
    {
        if (AnnouncementStatus::PendingPayment === $this->status) {
            $this->status = AnnouncementStatus::PaymentFailed;
        }
    }

    public function approve(User $reviewer, \DateTimeImmutable $at): void
    {
        $this->status = AnnouncementStatus::Approved;
        $this->reviewedAt = $at;
        $this->reviewedBy = $reviewer;
        $this->rejectionReason = null;
        $this->startsAt = $at;
        $this->endsAt = $at->modify(sprintf('+%d days', $this->durationDays));
    }

    public function reject(User $reviewer, \DateTimeImmutable $at, string $reason): void
    {
        $this->status = AnnouncementStatus::Rejected;
        $this->reviewedAt = $at;
        $this->reviewedBy = $reviewer;
        $this->rejectionReason = $reason;
        $this->startsAt = null;
        $this->endsAt = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTrackingToken(): string
    {
        return $this->trackingToken;
    }

    public function getCategory(): ?AnnouncementCategory
    {
        return $this->category;
    }

    public function setCategory(?AnnouncementCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

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

    public function isShowContact(): bool
    {
        return $this->showContact;
    }

    public function setShowContact(bool $showContact): static
    {
        $this->showContact = $showContact;

        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): static
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function isWithHomeCard(): bool
    {
        return $this->withHomeCard;
    }

    public function setWithHomeCard(bool $withHomeCard): static
    {
        $this->withHomeCard = $withHomeCard;

        return $this;
    }

    public function isWithPopup(): bool
    {
        return $this->withPopup;
    }

    public function setWithPopup(bool $withPopup): static
    {
        $this->withPopup = $withPopup;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): AnnouncementStatus
    {
        return $this->status;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function setTransactionReference(?string $transactionReference): static
    {
        $this->transactionReference = $transactionReference;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
