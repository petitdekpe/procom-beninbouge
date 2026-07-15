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
use ApiPlatform\Metadata\Put;
use App\Entity\Enum\AdSlotType;
use App\Repository\AdSlotRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Un espace publicitaire (bannière, native, contenu sponsorisé) vendu à un
 * Partner, avec son emplacement et sa période d'affichage. Les lectures
 * publiques (GetCollection/Get) ne renvoient que les emplacements actifs
 * et dans leur période d'affichage (voir ActiveAdSlotExtension) ; la
 * gestion complète (y compris les emplacements inactifs ou futurs) reste
 * disponible aux éditeurs/admins via Post/Put/Patch/Delete.
 */
#[ORM\Entity(repositoryClass: AdSlotRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_EDITOR')"),
        new Put(security: "is_granted('ROLE_EDITOR')"),
        new Patch(security: "is_granted('ROLE_EDITOR')"),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'placement' => 'exact', 'partner' => 'exact', 'active' => 'exact'])]
#[Assert\Callback(callback: 'validateTypeSpecificFields')]
class AdSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: AdSlotType::class)]
    #[Assert\NotNull]
    private ?AdSlotType $type = null;

    /**
     * Emplacement logique dans le site (ex: "homepage_top",
     * "article_sidebar", "between_articles").
     */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $placement = null;

    #[ORM\ManyToOne(targetEntity: Partner::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Partner $partner = null;

    // Pour les types banner/native : visuel et lien de destination.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $creativeUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetUrl = null;

    // Pour le type sponsored_content : l'article publi-reportage affiché.
    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Article $article = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function validateTypeSpecificFields(ExecutionContextInterface $context): void
    {
        if (AdSlotType::SponsoredContent === $this->type && !$this->article) {
            $context->buildViolation('Un contenu sponsorisé doit référencer un article (article).')
                ->atPath('article')
                ->addViolation();
        }

        if (\in_array($this->type, [AdSlotType::Banner, AdSlotType::Native], true)) {
            if (!$this->creativeUrl) {
                $context->buildViolation('Une bannière ou un format natif doit avoir un visuel (creativeUrl).')
                    ->atPath('creativeUrl')
                    ->addViolation();
            }
            if (!$this->targetUrl) {
                $context->buildViolation('Une bannière ou un format natif doit avoir un lien de destination (targetUrl).')
                    ->atPath('targetUrl')
                    ->addViolation();
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?AdSlotType
    {
        return $this->type;
    }

    public function setType(AdSlotType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPlacement(): ?string
    {
        return $this->placement;
    }

    public function setPlacement(string $placement): static
    {
        $this->placement = $placement;

        return $this;
    }

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getCreativeUrl(): ?string
    {
        return $this->creativeUrl;
    }

    public function setCreativeUrl(?string $creativeUrl): static
    {
        $this->creativeUrl = $creativeUrl;

        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
