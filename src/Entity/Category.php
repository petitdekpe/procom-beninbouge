<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\CategoryRepository;
use App\State\CachedCategoryCollectionProvider;
use App\State\CategoryDeleteProcessor;
use App\State\CategoryWriteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: CachedCategoryCollectionProvider::class,
            cacheHeaders: ['max_age' => 60, 'shared_max_age' => 3600, 'vary' => ['Authorization']],
        ),
        new Get(
            cacheHeaders: ['max_age' => 60, 'shared_max_age' => 3600, 'vary' => ['Authorization']],
        ),
        new Post(security: "is_granted('ROLE_EDITOR')", processor: CategoryWriteProcessor::class),
        new Put(security: "is_granted('ROLE_EDITOR')", processor: CategoryWriteProcessor::class),
        new Patch(security: "is_granted('ROLE_EDITOR')", processor: CategoryWriteProcessor::class),
        new Delete(security: "is_granted('ROLE_EDITOR')", processor: CategoryDeleteProcessor::class),
    ],
    paginationItemsPerPage: 30,
    paginationMaximumItemsPerPage: 100,
    paginationClientItemsPerPage: true,
)]
#[Assert\Callback(callback: 'validateParent')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $slug = null;

    /**
     * Short intro shown on the public rubrique page and used as the
     * source text for metaDescription when that's left blank.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Generated automatically from the name when left blank — see
     * App\Service\SeoMetadataGenerator, same pattern as Article/Page.
     */
    #[ORM\Column(length: 70, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $metaDescription = null;

    /**
     * Hex color (e.g. "#2B6B43") used for this category's badge/chip
     * wherever its content is shown — article cards, media listings, the
     * public rubrique page (App\Controller\CategoryController).
     */
    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être un code hexadécimal, ex. #2B6B43.')]
    private ?string $color = null;

    /**
     * Null for a top-level (root) category. A category can only nest one
     * level deep in practice, but the relation itself doesn't enforce
     * that — see validateParent() for what it does enforce (no
     * self-reference, no cycles).
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $parent = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function validateParent(ExecutionContextInterface $context): void
    {
        if (!$this->parent) {
            return;
        }

        for ($ancestor = $this->parent; null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $this) {
                $context->buildViolation('Une catégorie ne peut pas être sa propre sous-catégorie, même indirectement.')
                    ->atPath('parent')
                    ->addViolation();

                return;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }
}
