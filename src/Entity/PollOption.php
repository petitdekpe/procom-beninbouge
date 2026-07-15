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
use App\Repository\PollOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PollOptionRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_EDITOR')", denormalizationContext: ['groups' => ['poll_option:write']]),
        new Patch(security: "is_granted('ROLE_EDITOR')", denormalizationContext: ['groups' => ['poll_option:write']]),
        new Delete(security: "is_granted('ROLE_EDITOR')"),
    ],
    normalizationContext: ['groups' => ['poll_option:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['poll' => 'exact'])]
class PollOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['poll_option:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['poll_option:read', 'poll_option:write'])]
    private ?string $label = null;

    #[ORM\ManyToOne(targetEntity: Poll::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['poll_option:read', 'poll_option:write'])]
    private ?Poll $poll = null;

    #[ORM\Column]
    #[Groups(['poll_option:read'])]
    private int $votesCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }

    public function getVotesCount(): int
    {
        return $this->votesCount;
    }

    public function setVotesCount(int $votesCount): static
    {
        $this->votesCount = $votesCount;

        return $this;
    }

    public function incrementVotesCount(): static
    {
        ++$this->votesCount;

        return $this;
    }
}
