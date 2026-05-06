<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use App\Repository\ClaimEdgeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A directed edge in the weight-of-evidence claim graph.
 * relationship: supports | refutes | qualifies | requires
 */
#[ORM\Entity(repositoryClass: ClaimEdgeRepository::class)]
#[ORM\Table(name: 'claim_edges')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get()],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class ClaimEdge
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: ClaimNode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ClaimNode $fromClaim;

    #[ORM\ManyToOne(targetEntity: ClaimNode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['read', 'write'])]
    private ClaimNode $toClaim;

    /** supports | refutes | qualifies | requires */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['supports', 'refutes', 'qualifies', 'requires'])]
    #[Groups(['read', 'write'])]
    private string $relationship = 'supports';

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid { return $this->id; }
    public function getFromClaim(): ClaimNode { return $this->fromClaim; }
    public function setFromClaim(ClaimNode $v): static { $this->fromClaim = $v; return $this; }
    public function getToClaim(): ClaimNode { return $this->toClaim; }
    public function setToClaim(ClaimNode $v): static { $this->toClaim = $v; return $this; }
    public function getRelationship(): string { return $this->relationship; }
    public function setRelationship(string $v): static { $this->relationship = $v; return $this; }
}
