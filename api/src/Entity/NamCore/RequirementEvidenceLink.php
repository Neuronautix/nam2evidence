<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use App\Entity\EvidenceItem;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Explicit evidence edge supporting (or qualifying/contradicting) a requirement
 * assessment. The first v0.2 foundation links the existing EvidenceItem model;
 * later evidence types can be added without changing assessment semantics.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_requirement_evidence_link',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_requirement_evidence_relationship', columns: ['assessment_id', 'evidence_item_id', 'relationship']),
    ]
)]
#[ORM\Index(name: 'idx_requirement_evidence_assessment', columns: ['assessment_id'])]
#[ORM\Index(name: 'idx_requirement_evidence_item', columns: ['evidence_item_id'])]
class RequirementEvidenceLink
{
    public const REL_SUPPORTS = 'supports';
    public const REL_PARTIALLY_SUPPORTS = 'partially_supports';
    public const REL_CONTRADICTS = 'contradicts';
    public const REL_CONTEXTUALIZES = 'contextualizes';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: RequirementAssessment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RequirementAssessment $assessment;

    #[ORM\ManyToOne(targetEntity: EvidenceItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EvidenceItem $evidenceItem;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [
        self::REL_SUPPORTS,
        self::REL_PARTIALLY_SUPPORTS,
        self::REL_CONTRADICTS,
        self::REL_CONTEXTUALIZES,
    ])]
    private string $relationship = self::REL_SUPPORTS;

    /** Source/transformation/selection provenance for why this edge exists. */
    #[ORM\Column(type: 'json')]
    private array $provenance = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getAssessment(): RequirementAssessment { return $this->assessment; }
    public function setAssessment(RequirementAssessment $v): static { $this->assessment = $v; return $this; }
    public function getEvidenceItem(): EvidenceItem { return $this->evidenceItem; }
    public function setEvidenceItem(EvidenceItem $v): static { $this->evidenceItem = $v; return $this; }
    public function getRelationship(): string { return $this->relationship; }
    public function setRelationship(string $v): static { $this->relationship = $v; return $this; }
    public function getProvenance(): array { return $this->provenance; }
    public function setProvenance(array $v): static { $this->provenance = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    #[Assert\Callback]
    public function validateScope(ExecutionContextInterface $context): void
    {
        if (!isset($this->assessment, $this->evidenceItem)) {
            return;
        }

        $assessmentProject = $this->assessment->getProject()->getId()->toRfc4122();
        $evidenceProject = $this->evidenceItem->getStudy()->getProject()->getId()->toRfc4122();
        if ($assessmentProject !== $evidenceProject) {
            $context->buildViolation('RequirementEvidenceLink evidenceItem must belong to the assessment project.')
                ->atPath('evidenceItem')->addViolation();
            return;
        }

        $assessmentCou = $this->assessment->getContextOfUse()->getId()->toRfc4122();
        $evidenceCou = $this->evidenceItem->getStudy()->getContextOfUse()->getId()->toRfc4122();
        if ($assessmentCou !== $evidenceCou) {
            $context->buildViolation('RequirementEvidenceLink evidenceItem must belong to the assessment Context of Use.')
                ->atPath('evidenceItem')->addViolation();
        }
    }
}
