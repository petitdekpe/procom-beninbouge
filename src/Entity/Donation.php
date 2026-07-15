<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Enum\DonationStatus;
use App\Repository\DonationRepository;
use App\State\DonationInitiateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un don intégré à FedaPay. La création se fait exclusivement via
 * l'opération dédiée /donations/initiate (qui ouvre la transaction
 * FedaPay et renvoie l'URL de paiement) ; le statut est ensuite confirmé
 * de façon asynchrone par le webhook FedaPay
 * (voir App\Controller\FedaPayWebhookController).
 */
#[ORM\Entity(repositoryClass: DonationRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            uriTemplate: '/donations/initiate',
            denormalizationContext: ['groups' => ['donation:create']],
            processor: DonationInitiateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['donation:read']],
)]
class Donation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['donation:read'])]
    private ?int $id = null;

    /**
     * Montant en FCFA (XOF), unité entière (pas de centimes).
     */
    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['donation:read', 'donation:create'])]
    private ?int $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['donation:read', 'donation:create'])]
    private string $currency = 'XOF';

    #[ORM\Column(length: 20, enumType: DonationStatus::class)]
    #[Groups(['donation:read'])]
    private DonationStatus $status = DonationStatus::Pending;

    #[ORM\Column(length: 100, nullable: true, unique: true)]
    #[Groups(['donation:read'])]
    private ?string $transactionReference = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Groups(['donation:read', 'donation:create'])]
    private ?string $donorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Groups(['donation:read', 'donation:create'])]
    private ?string $donorEmail = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['donation:read', 'donation:create'])]
    private ?string $donorPhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['donation:read', 'donation:create'])]
    private ?string $message = null;

    /**
     * URL de paiement FedaPay générée à l'initiation. Transitoire :
     * non persistée, utile uniquement dans la réponse de /donations/initiate.
     */
    #[Groups(['donation:read'])]
    private ?string $paymentUrl = null;

    #[ORM\Column]
    #[Groups(['donation:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?int
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

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): DonationStatus
    {
        return $this->status;
    }

    public function setStatus(DonationStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getDonorName(): ?string
    {
        return $this->donorName;
    }

    public function setDonorName(?string $donorName): static
    {
        $this->donorName = $donorName;

        return $this;
    }

    public function getDonorEmail(): ?string
    {
        return $this->donorEmail;
    }

    public function setDonorEmail(?string $donorEmail): static
    {
        $this->donorEmail = $donorEmail;

        return $this;
    }

    public function getDonorPhone(): ?string
    {
        return $this->donorPhone;
    }

    public function setDonorPhone(?string $donorPhone): static
    {
        $this->donorPhone = $donorPhone;

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

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function setPaymentUrl(?string $paymentUrl): static
    {
        $this->paymentUrl = $paymentUrl;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
