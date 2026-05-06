<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ClaimNodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A structured weight-of-evidence claim node.
 * Confidence levels follow the four-tier NAMO regulatory support vocabulary:
 *   exploratory | supportive | decision_informing | potentially_pivotal
 *
 * Every claim starts as human_review_required; export is blocked until all
 * claims in a project are approved.
 */
#[ORM\Entity(repositoryClass: ClaimNodeRepository::class)]
#[ORM\Table(name: 'claim_nodes')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class ClaimNode
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['read'])]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $claimId = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read', 'write'])]
    private Project $project;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $claimText = '';

    /** mechanistic | empirical | comparative | predictive */
    #[ORM\Column(length: 30)]
    #[Groups(['read', 'write'])]
    private string $claimType = 'empirical';

    #[ORM\ManyToOne(targetEntity: ContextOfUseCard::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read', 'write'])]
    private ContextOfUseCard $contextOfUse;

    /** exploratory | supportive | decision_informing | potentially_pivotal */
    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: ['exploratory', 'supportive', 'decision_informing', 'potentially_pivotal'])]
    #[Groups(['read', 'write'])]
    private string $confidence = 'exploratory';

    /** JSONB array of evidence_id references that support this claim */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $supportingEvidence = [];

    /** JSONB array of evidence_id references that contradict this claim */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $contradictoryEvidence = [];

    /** JSONB array of limitation strings */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $limitations = [];

    /** JSONB array of eCTD section codes, e.g. ["4.2.3.7.3", "2.6.2"] */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $ectdTargetSections = [];

    /** pending | human_review_required | approved | rejected */
    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: ['pending', 'human_review_required', 'approved', 'rejected'])]
    #[Groups(['read', 'write'])]
    private string $reviewStatus = 'human_review_required';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read', 'write'])]
    private ?ClaimNode $parentClaim = null;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid { return $this->id; }
    public function getClaimId(): string { return $this->claimId; }
    public function setClaimId(string $v): static { $this->claimId = $v; return $this; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getClaimText(): string { return $this->claimText; }
    public function setClaimText(string $v): static { $this->claimText = $v; return $this; }
    public function getClaimType(): string { return $this->claimType; }
    public function setClaimType(string $v): static { $this->claimType = $v; return $this; }
    public function getContextOfUse(): ContextOfUseCard { return $this->contextOfUse; }
    public function setContextOfUse(ContextOfUseCard $v): static { $this->contextOfUse = $v; return $this; }
    public function getConfidence(): string { return $this->confidence; }
    public function setConfidence(string $v): static { $this->confidence = $v; return $this; }
    public function getSupportingEvidence(): array { return $this->supportingEvidence; }
    public function setSupportingEvidence(array $v): static { $this->supportingEvidence = $v; return $this; }
    public function getContradictoryEvidence(): array { return $this->contradictoryEvidence; }
    public function setContradictoryEvidence(array $v): static { $this->contradictoryEvidence = $v; return $this; }
    public function getLimitations(): array { return $this->limitations; }
    public function setLimitations(array $v): static { $this->limitations = $v; return $this; }
    public function getEctdTargetSections(): array { return $this->ectdTargetSections; }
    public function setEctdTargetSections(array $v): static { $this->ectdTargetSections = $v; return $this; }
    public function getReviewStatus(): string { return $this->reviewStatus; }
    public function setReviewStatus(string $v): static { $this->reviewStatus = $v; return $this; }
    public function getParentClaim(): ?ClaimNode { return $this->parentClaim; }
    public function setParentClaim(?ClaimNode $v): static { $this->parentClaim = $v; return $this; }

    #[Assert\Callback]
    public function validateProjectConsistency(ExecutionContextInterface $context): void
    {
        if ($this->contextOfUse->getProject()->getId()->toRfc4122() !== $this->project->getId()->toRfc4122()) {
            $context->buildViolation('ClaimNode contextOfUse must belong to the same project.')
                ->atPath('contextOfUse')
                ->addViolation();
        }
    }
}
