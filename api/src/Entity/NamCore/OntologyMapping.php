<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

/**
 * A human-reviewable mapping from a source value on a NAM-CORE entity/field to a
 * controlled OntologyTerm. Mappings move unmapped → suggested → approved/rejected
 * under explicit human review; "AI-ready" status is blocked while mandatory
 * mappings remain unresolved.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_ontology_mapping')]
#[ORM\Index(name: 'idx_ontmap_project', columns: ['project_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'OntologyMapping',
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['ontology:read']],
)]
class OntologyMapping
{
    public const STATUS_UNMAPPED = 'unmapped';
    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['ontology:read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ontology:read'])]
    private Project $project;

    /** Which kind of thing is being mapped: cell_type, anatomy, chemical, assay, disease, unit, species, endpoint. */
    #[ORM\Column(length: 60)]
    #[Groups(['ontology:read'])]
    private string $sourceEntityType = '';

    /** Optional reference to the specific record (ULID string or business key). */
    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['ontology:read'])]
    private ?string $sourceEntityId = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['ontology:read'])]
    private ?string $sourceField = null;

    #[ORM\Column(length: 255)]
    #[Groups(['ontology:read'])]
    private string $sourceValue = '';

    #[ORM\ManyToOne(targetEntity: OntologyTerm::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ontology:read'])]
    private ?OntologyTerm $ontologyTerm = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['ontology:read'])]
    private float $mappingConfidence = 0.0;

    /** @see self::STATUS_* */
    #[ORM\Column(length: 20)]
    #[Groups(['ontology:read'])]
    private string $mappingStatus = self::STATUS_UNMAPPED;

    /** Whether this mapping is mandatory for AI-ready status. */
    #[ORM\Column]
    #[Groups(['ontology:read'])]
    private bool $mandatory = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ontology:read'])]
    private ?string $reviewerNote = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['ontology:read'])]
    private ?string $reviewedBy = null;

    #[ORM\Column]
    #[Groups(['ontology:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['ontology:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getSourceEntityType(): string { return $this->sourceEntityType; }
    public function setSourceEntityType(string $v): static { $this->sourceEntityType = $v; return $this; }
    public function getSourceEntityId(): ?string { return $this->sourceEntityId; }
    public function setSourceEntityId(?string $v): static { $this->sourceEntityId = $v; return $this; }
    public function getSourceField(): ?string { return $this->sourceField; }
    public function setSourceField(?string $v): static { $this->sourceField = $v; return $this; }
    public function getSourceValue(): string { return $this->sourceValue; }
    public function setSourceValue(string $v): static { $this->sourceValue = $v; return $this; }
    public function getOntologyTerm(): ?OntologyTerm { return $this->ontologyTerm; }
    public function setOntologyTerm(?OntologyTerm $v): static { $this->ontologyTerm = $v; return $this; }
    public function getMappingConfidence(): float { return $this->mappingConfidence; }
    public function setMappingConfidence(float $v): static { $this->mappingConfidence = $v; return $this; }
    public function getMappingStatus(): string { return $this->mappingStatus; }
    public function setMappingStatus(string $v): static { $this->mappingStatus = $v; return $this; }
    public function isMandatory(): bool { return $this->mandatory; }
    public function setMandatory(bool $v): static { $this->mandatory = $v; return $this; }
    public function getReviewerNote(): ?string { return $this->reviewerNote; }
    public function setReviewerNote(?string $v): static { $this->reviewerNote = $v; return $this; }
    public function getReviewedBy(): ?string { return $this->reviewedBy; }
    public function setReviewedBy(?string $v): static { $this->reviewedBy = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
