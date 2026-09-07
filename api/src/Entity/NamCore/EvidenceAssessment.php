<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\ContextOfUseCard;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A dated scientific, peer-review, regulatory, or qualification assessment of
 * a NAM method in a specific Context of Use.
 *
 * Acceptance belongs here, not on the method itself: one method may be accepted
 * for one CoU and exploratory for another.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_evidence_assessment')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'EvidenceAssessment',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class EvidenceAssessment
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: NAMMethod::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?NAMMethod $namMethod = null;

    #[ORM\ManyToOne(targetEntity: ContextOfUseCard::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?ContextOfUseCard $contextOfUse = null;

    #[ORM\ManyToOne(targetEntity: SourceProject::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?SourceProject $sourceProject = null;

    /** scientific_validation | peer_review | regulatory_review | qualification | standard_acceptance | internal_review */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $assessmentType = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $assessorOrganization = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $authority = null;

    /** proposed | under_review | informative | accepted | qualified | rejected | withdrawn */
    #[ORM\Column(length: 40)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $status = 'under_review';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $conclusion = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?\DateTimeImmutable $assessedAt = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $sourceUrl = null;

    /** Evidence IDs, study IDs, document identifiers, or other references used by the assessment. */
    #[ORM\Column(type: 'json')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private array $evidenceBasis = [];

    /** Conditions, restrictions, and limitations attached to the assessment. */
    #[ORM\Column(type: 'json')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private array $conditions = [];

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getNamMethod(): ?NAMMethod { return $this->namMethod; }
    public function setNamMethod(?NAMMethod $v): static { $this->namMethod = $v; return $this; }
    public function getContextOfUse(): ?ContextOfUseCard { return $this->contextOfUse; }
    public function setContextOfUse(?ContextOfUseCard $v): static { $this->contextOfUse = $v; return $this; }
    public function getSourceProject(): ?SourceProject { return $this->sourceProject; }
    public function setSourceProject(?SourceProject $v): static { $this->sourceProject = $v; return $this; }
    public function getAssessmentType(): string { return $this->assessmentType; }
    public function setAssessmentType(string $v): static { $this->assessmentType = $v; return $this; }
    public function getAssessorOrganization(): string { return $this->assessorOrganization; }
    public function setAssessorOrganization(string $v): static { $this->assessorOrganization = $v; return $this; }
    public function getAuthority(): ?string { return $this->authority; }
    public function setAuthority(?string $v): static { $this->authority = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getConclusion(): ?string { return $this->conclusion; }
    public function setConclusion(?string $v): static { $this->conclusion = $v; return $this; }
    public function getAssessedAt(): ?\DateTimeImmutable { return $this->assessedAt; }
    public function setAssessedAt(?\DateTimeImmutable $v): static { $this->assessedAt = $v; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): static { $this->sourceUrl = $v; return $this; }
    public function getEvidenceBasis(): array { return $this->evidenceBasis; }
    public function setEvidenceBasis(array $v): static { $this->evidenceBasis = $v; return $this; }
    public function getConditions(): array { return $this->conditions; }
    public function setConditions(array $v): static { $this->conditions = $v; return $this; }
}
