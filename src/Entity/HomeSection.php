<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Entity\Enum\HomeSectionType;
use App\Repository\HomeSectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One block of the public homepage (App\Controller\HomeController). The
 * fixed set of types is seeded once by migration — admins never create or
 * delete a HomeSection, only reorder (position) and show/hide (enabled)
 * the existing ones via App\Controller\Admin\HomeSectionController.
 *
 * type is no longer unique: HomeSectionType::CategoryHighlight can appear
 * several times (one row per configurable "rubrique en avant" block),
 * each pointing at its own $category — see category below.
 */
#[ORM\Entity(repositoryClass: HomeSectionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Patch(security: "is_granted('ROLE_EDITOR')"),
    ],
)]
class HomeSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, enumType: HomeSectionType::class)]
    #[Assert\NotNull]
    private ?HomeSectionType $type = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $enabled = true;

    /**
     * Only meaningful when type is CategoryHighlight — the rubrique whose
     * latest articles this block shows. Left null until an admin picks
     * one; the section simply doesn't render until then (see
     * App\Controller\HomeController).
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?HomeSectionType
    {
        return $this->type;
    }

    public function setType(HomeSectionType $type): static
    {
        $this->type = $type;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
