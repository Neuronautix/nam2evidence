<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One source-cited expectation represented inside a specific GuidanceProfile
 * version. requirementKey is stable within the logical profile across versions
 * where the curator determines that the underlying expectation is continuous.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_guidance_requirement',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_guidance_requirement_profile_key', columns: ['profile_id', 'requirement_key']),
    ]
)]
#[ORM\Index(name: 'idx_guidance_requirement_profile', columns: ['profile_id'])]
#[ORM\Index(name: 'idx_guidance_requirement_domain', columns: ['evidence_domain'])]
#[ORM\HasLifecycleCallbacks]
class GuidanceRequirement
{
    public const IMPORTANCE_UNSPECIFIED = 'unspecified';
    public const IMPORTANCE_INFORMATIONAL = 'informational';
    public const IMPORTANCE_RECOMMENDED = 'recommended';
    public const IMPORTANCE_EXPECTED = 'expected';
    public const IMPORTANCE_REQUIRED = 'required';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: GuidanceProfile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private GuidanceProfile $profile;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $requirementKey = '';

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private string $title = '';

    /** Curated paraphrase/representation of the source expectation. */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $requirementText = '';

    /**
     * Neutral evidence-domain vocabulary. Kept as a string so new authorities
     * can add domains without a schema migration; profiles should document their
     * mapping choices.
     */
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $evidenceDomain = '';

    /** Machine-readable conditions used only to decide applicability/candidate checks. */
    #[ORM\Column(type: 'json')]
    private array $applicabilityRules = [];

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [
        self::IMPORTANCE_UNSPECIFIED,
        self::IMPORTANCE_INFORMATIONAL,
        self::IMPORTANCE_RECOMMENDED,
        self::IMPORTANCE_EXPECTED,
        self::IMPORTANCE_REQUIRED,
    ])]
    private string $importance = self::IMPORTANCE_UNSPECIFIED;

    /** Human-readable source location: section, page, paragraph, table, etc. */
    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private string $sourceLocator = '';

    /** Curator note explaining interpretation limits or conditionality. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $interpretationNote = null;

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
    public function getProfile(): GuidanceProfile { return $this->profile; }
    public function setProfile(GuidanceProfile $v): static { $this->profile = $v; return $this; }
    public function getRequirementKey(): string { return $this->requirementKey; }
    public function setRequirementKey(string $v): static { $this->requirementKey = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getRequirementText(): string { return $this->requirementText; }
    public function setRequirementText(string $v): static { $this->requirementText = $v; return $this; }
    public function getEvidenceDomain(): string { return $this->evidenceDomain; }
    public function setEvidenceDomain(string $v): static { $this->evidenceDomain = $v; return $this; }
    public function getApplicabilityRules(): array { return $this->applicabilityRules; }
    public function setApplicabilityRules(array $v): static { $this->applicabilityRules = $v; return $this; }
    public function getImportance(): string { return $this->importance; }
    public function setImportance(string $v): static { $this->importance = $v; return $this; }
    public function getSourceLocator(): string { return $this->sourceLocator; }
    public function setSourceLocator(string $v): static { $this->sourceLocator = $v; return $this; }
    public function getInterpretationNote(): ?string { return $this->interpretationNote; }
    public function setInterpretationNote(?string $v): static { $this->interpretationNote = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
