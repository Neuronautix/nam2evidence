<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use App\Entity\ClaimNode;
use App\Entity\EvidenceItem;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A human-decided edge between a captured ExternalStatement and exactly one
 * project record: a RequirementAssessment, a ClaimNode or an EvidenceItem.
 *
 * Links are born `suggested` (or `human_entered`) and count for nothing until a
 * named reviewer approves them. `not_comparable` is a first-class outcome so a
 * reviewer can record *why* a superficially similar statement does not apply
 * to this Context of Use.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_external_statement_link')]
#[ORM\Index(name: 'idx_extlink_statement', columns: ['external_statement_id'])]
#[ORM\Index(name: 'idx_extlink_assessment', columns: ['requirement_assessment_id'])]
#[ORM\Index(name: 'idx_extlink_claim', columns: ['claim_id'])]
#[ORM\Index(name: 'idx_extlink_evidence', columns: ['evidence_item_id'])]
#[ORM\HasLifecycleCallbacks]
class ExternalStatementLink
{
    public const REL_SUPPORTS = 'supports';
    public const REL_PARTIALLY_SUPPORTS = 'partially_supports';
    public const REL_CONTRADICTS = 'contradicts';
    public const REL_CONTEXTUALIZES = 'contextualizes';
    public const REL_NOT_COMPARABLE = 'not_comparable';

    public const RELATIONSHIPS = [
        self::REL_SUPPORTS, self::REL_PARTIALLY_SUPPORTS, self::REL_CONTRADICTS,
        self::REL_CONTEXTUALIZES, self::REL_NOT_COMPARABLE,
    ];

    public const ORIGIN_DETERMINISTIC = 'deterministic_rule';
    public const ORIGIN_MACHINE_SUGGESTION = 'machine_suggestion';
    public const ORIGIN_HUMAN = 'human_entered';

    public const REVIEW_SUGGESTED = 'suggested';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    public const TARGET_REQUIREMENT_ASSESSMENT = 'requirement_assessment';
    public const TARGET_CLAIM = 'claim';
    public const TARGET_EVIDENCE_ITEM = 'evidence_item';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: ExternalStatement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExternalStatement $externalStatement;

    #[ORM\ManyToOne(targetEntity: RequirementAssessment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?RequirementAssessment $requirementAssessment = null;

    #[ORM\ManyToOne(targetEntity: ClaimNode::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ClaimNode $claim = null;

    #[ORM\ManyToOne(targetEntity: EvidenceItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?EvidenceItem $evidenceItem = null;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: self::RELATIONSHIPS)]
    private string $relationship = self::REL_CONTEXTUALIZES;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::ORIGIN_DETERMINISTIC, self::ORIGIN_MACHINE_SUGGESTION, self::ORIGIN_HUMAN])]
    private string $linkOrigin = self::ORIGIN_HUMAN;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::REVIEW_SUGGESTED, self::REVIEW_APPROVED, self::REVIEW_REJECTED])]
    private string $reviewStatus = self::REVIEW_SUGGESTED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewerNote = null;

    /** Why the statement is, or is not, comparable with this Context of Use. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comparabilityNote = null;

    #[ORM\Column(type: 'json')]
    private array $provenance = [];

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
    public function getExternalStatement(): ExternalStatement { return $this->externalStatement; }
    public function setExternalStatement(ExternalStatement $v): static { $this->externalStatement = $v; return $this; }
    public function getRequirementAssessment(): ?RequirementAssessment { return $this->requirementAssessment; }
    public function setRequirementAssessment(?RequirementAssessment $v): static { $this->requirementAssessment = $v; return $this; }
    public function getClaim(): ?ClaimNode { return $this->claim; }
    public function setClaim(?ClaimNode $v): static { $this->claim = $v; return $this; }
    public function getEvidenceItem(): ?EvidenceItem { return $this->evidenceItem; }
    public function setEvidenceItem(?EvidenceItem $v): static { $this->evidenceItem = $v; return $this; }
    public function getRelationship(): string { return $this->relationship; }
    public function setRelationship(string $v): static { $this->relationship = $v; return $this; }
    public function getLinkOrigin(): string { return $this->linkOrigin; }
    public function setLinkOrigin(string $v): static { $this->linkOrigin = $v; return $this; }
    public function getReviewStatus(): string { return $this->reviewStatus; }
    public function setReviewStatus(string $v): static { $this->reviewStatus = $v; return $this; }
    public function getReviewedBy(): ?string { return $this->reviewedBy; }
    public function setReviewedBy(?string $v): static { $this->reviewedBy = $v; return $this; }
    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeImmutable $v): static { $this->reviewedAt = $v; return $this; }
    public function getReviewerNote(): ?string { return $this->reviewerNote; }
    public function setReviewerNote(?string $v): static { $this->reviewerNote = $v; return $this; }
    public function getComparabilityNote(): ?string { return $this->comparabilityNote; }
    public function setComparabilityNote(?string $v): static { $this->comparabilityNote = $v; return $this; }
    public function getProvenance(): array { return $this->provenance; }
    public function setProvenance(array $v): static { $this->provenance = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getTargetType(): string
    {
        if ($this->requirementAssessment !== null) return self::TARGET_REQUIREMENT_ASSESSMENT;
        if ($this->claim !== null) return self::TARGET_CLAIM;
        return self::TARGET_EVIDENCE_ITEM;
    }

    public function isApproved(): bool
    {
        return $this->reviewStatus === self::REVIEW_APPROVED;
    }

    public function isSupportive(): bool
    {
        return in_array($this->relationship, [self::REL_SUPPORTS, self::REL_PARTIALLY_SUPPORTS], true);
    }

    private function targetProjectId(): ?string
    {
        if ($this->requirementAssessment !== null) return $this->requirementAssessment->getProject()->getId()->toRfc4122();
        if ($this->claim !== null) return $this->claim->getProject()->getId()->toRfc4122();
        if ($this->evidenceItem !== null) return $this->evidenceItem->getStudy()->getProject()->getId()->toRfc4122();
        return null;
    }

    #[Assert\Callback]
    public function validateShape(ExecutionContextInterface $context): void
    {
        $targets = (int) ($this->requirementAssessment !== null) + (int) ($this->claim !== null) + (int) ($this->evidenceItem !== null);
        if ($targets !== 1) {
            $context->buildViolation('ExternalStatementLink must target exactly one of requirement_assessment, claim or evidence_item.')
                ->atPath('target')->addViolation();
            return;
        }

        if (!isset($this->externalStatement)) {
            return;
        }

        $statementProject = $this->externalStatement->getProject()->getId()->toRfc4122();
        if ($this->targetProjectId() !== $statementProject) {
            $context->buildViolation('ExternalStatementLink target must belong to the statement project.')
                ->atPath('target')->addViolation();
        }

        if ($this->reviewStatus !== self::REVIEW_SUGGESTED && ($this->reviewedBy === null || trim($this->reviewedBy) === '')) {
            $context->buildViolation('A reviewed ExternalStatementLink must record reviewed_by.')
                ->atPath('reviewedBy')->addViolation();
        }

        if ($this->reviewStatus === self::REVIEW_APPROVED && $this->isSupportive() && !$this->externalStatement->isEligibleForSupport()) {
            $context->buildViolation('Only a public statement whose capture a human verified may be approved as supporting evidence.')
                ->atPath('relationship')->addViolation();
        }
    }
}
