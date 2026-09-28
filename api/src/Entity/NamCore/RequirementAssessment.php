<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use App\Entity\ContextOfUseCard;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Context-of-Use-specific assessment of one versioned guidance requirement.
 *
 * Status expresses evidence coverage only. It must never be interpreted as
 * regulatory acceptance, scientific validation, or submission readiness.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_requirement_assessment',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_requirement_assessment_scope', columns: ['project_id', 'context_of_use_id', 'requirement_id']),
    ]
)]
#[ORM\Index(name: 'idx_requirement_assessment_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_requirement_assessment_cou', columns: ['context_of_use_id'])]
#[ORM\Index(name: 'idx_requirement_assessment_requirement', columns: ['requirement_id'])]
#[ORM\HasLifecycleCallbacks]
class RequirementAssessment
{
    public const STATUS_SUPPORTED = 'supported';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_MISSING = 'missing';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';
    public const STATUS_REQUIRES_HUMAN = 'requires_human_assessment';

    public const ORIGIN_DETERMINISTIC = 'deterministic_rule';
    public const ORIGIN_MACHINE_SUGGESTION = 'machine_suggestion';
    public const ORIGIN_HUMAN = 'human_entered';

    public const REVIEW_UNREVIEWED = 'unreviewed';
    public const REVIEW_HUMAN_REQUIRED = 'human_review_required';
    public const REVIEW_HUMAN_REVIEWED = 'human_reviewed';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: ContextOfUseCard::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ContextOfUseCard $contextOfUse;

    #[ORM\ManyToOne(targetEntity: GuidanceRequirement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private GuidanceRequirement $requirement;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [
        self::STATUS_SUPPORTED,
        self::STATUS_PARTIAL,
        self::STATUS_MISSING,
        self::STATUS_NOT_APPLICABLE,
        self::STATUS_REQUIRES_HUMAN,
    ])]
    private string $status = self::STATUS_REQUIRES_HUMAN;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rationale = null;

    /** How the assessment was produced; separate from whether a human reviewed it. */
    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::ORIGIN_DETERMINISTIC, self::ORIGIN_MACHINE_SUGGESTION, self::ORIGIN_HUMAN])]
    private string $assessmentOrigin = self::ORIGIN_HUMAN;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::REVIEW_UNREVIEWED, self::REVIEW_HUMAN_REQUIRED, self::REVIEW_HUMAN_REVIEWED])]
    private string $reviewStatus = self::REVIEW_HUMAN_REQUIRED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewerComment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getContextOfUse(): ContextOfUseCard { return $this->contextOfUse; }
    public function setContextOfUse(ContextOfUseCard $v): static { $this->contextOfUse = $v; return $this; }
    public function getRequirement(): GuidanceRequirement { return $this->requirement; }
    public function setRequirement(GuidanceRequirement $v): static { $this->requirement = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getRationale(): ?string { return $this->rationale; }
    public function setRationale(?string $v): static { $this->rationale = $v; return $this; }
    public function getAssessmentOrigin(): string { return $this->assessmentOrigin; }
    public function setAssessmentOrigin(string $v): static { $this->assessmentOrigin = $v; return $this; }
    public function getReviewStatus(): string { return $this->reviewStatus; }
    public function setReviewStatus(string $v): static { $this->reviewStatus = $v; return $this; }
    public function getReviewedBy(): ?string { return $this->reviewedBy; }
    public function setReviewedBy(?string $v): static { $this->reviewedBy = $v; return $this; }
    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeImmutable $v): static { $this->reviewedAt = $v; return $this; }
    public function getReviewerComment(): ?string { return $this->reviewerComment; }
    public function setReviewerComment(?string $v): static { $this->reviewerComment = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[Assert\Callback]
    public function validateScope(ExecutionContextInterface $context): void
    {
        if (isset($this->project, $this->contextOfUse)
            && $this->contextOfUse->getProject()->getId()->toRfc4122() !== $this->project->getId()->toRfc4122()) {
            $context->buildViolation('RequirementAssessment contextOfUse must belong to the same project as the assessment.')
                ->atPath('contextOfUse')->addViolation();
        }

        if ($this->reviewStatus === self::REVIEW_HUMAN_REVIEWED && ($this->reviewedBy === null || trim($this->reviewedBy) === '')) {
            $context->buildViolation('A human-reviewed assessment must identify reviewedBy.')
                ->atPath('reviewedBy')->addViolation();
        }
    }
}
