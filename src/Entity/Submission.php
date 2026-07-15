<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Enum\SubmissionStatus;
use App\Entity\Enum\SubmissionType;
use App\Repository\SubmissionRepository;
use App\State\SubmissionDeleteProcessor;
use App\State\SubmissionModerateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * La soumission citoyenne (proposition de sujet / signalement d'événement)
 * reste ouverte sans authentification ; seule sa modération et sa
 * suppression sont réservées aux éditeurs/admins.
 */
#[ORM\Entity(repositoryClass: SubmissionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(denormalizationContext: ['groups' => ['submission:create']]),
        new Patch(
            uriTemplate: '/submissions/{id}/moderate',
            security: "is_granted('ROLE_EDITOR')",
            denormalizationContext: ['groups' => ['submission:moderate']],
            processor: SubmissionModerateProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_EDITOR')", processor: SubmissionDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['submission:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'status' => 'exact'])]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['submission:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: SubmissionType::class)]
    #[Assert\NotNull]
    #[Groups(['submission:read', 'submission:create'])]
    private ?SubmissionType $type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['submission:read', 'submission:create'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['submission:read', 'submission:create'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['submission:read', 'submission:create'])]
    private ?string $location = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['submission:read', 'submission:create'])]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    #[Groups(['submission:read', 'submission:create'])]
    private ?string $submitterName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Groups(['submission:read', 'submission:create'])]
    private ?string $submitterEmail = null;

    #[ORM\Column(length: 20, enumType: SubmissionStatus::class)]
    #[Groups(['submission:read', 'submission:moderate'])]
    private SubmissionStatus $status = SubmissionStatus::New;

    #[ORM\Column]
    #[Groups(['submission:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?SubmissionType
    {
        return $this->type;
    }

    public function setType(SubmissionType $type): static
    {
        $this->type = $type;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
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

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(?\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getSubmitterName(): ?string
    {
        return $this->submitterName;
    }

    public function setSubmitterName(string $submitterName): static
    {
        $this->submitterName = $submitterName;

        return $this;
    }

    public function getSubmitterEmail(): ?string
    {
        return $this->submitterEmail;
    }

    public function setSubmitterEmail(?string $submitterEmail): static
    {
        $this->submitterEmail = $submitterEmail;

        return $this;
    }

    public function getStatus(): SubmissionStatus
    {
        return $this->status;
    }

    public function setStatus(SubmissionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
