<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\PollVoteRepository;
use App\State\PollVoteCreateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PollVoteRepository::class)]
#[ORM\Table(options: ['engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'uniq_poll_vote_poll_voter', columns: ['poll_id', 'voter_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_READER')",
            denormalizationContext: ['groups' => ['poll_vote:create']],
            processor: PollVoteCreateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['poll_vote:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['poll' => 'exact', 'option' => 'exact'])]
class PollVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['poll_vote:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poll::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['poll_vote:read', 'poll_vote:create'])]
    private ?Poll $poll = null;

    #[ORM\ManyToOne(targetEntity: PollOption::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['poll_vote:read', 'poll_vote:create'])]
    private ?PollOption $option = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['poll_vote:read'])]
    private ?User $voter = null;

    #[ORM\Column]
    #[Groups(['poll_vote:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOption(): ?PollOption
    {
        return $this->option;
    }

    public function setOption(?PollOption $option): static
    {
        $this->option = $option;

        return $this;
    }

    public function getVoter(): ?User
    {
        return $this->voter;
    }

    public function setVoter(?User $voter): static
    {
        $this->voter = $voter;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
