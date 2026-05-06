<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\NAMStudyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A NAMO-aligned NAM study record. Stores structured metadata covering the model system,
 * experimental design, assay metadata, data outputs, and provenance.
 * All compound/array fields are stored as JSONB for flexibility.
 */
#[ORM\Entity(repositoryClass: NAMStudyRepository::class)]
#[ORM\Table(name: 'nam_studies')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()]
)]
class NAMStudy
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $studyId = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: ContextOfUseCard::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ContextOfUseCard $contextOfUse;

    #[ORM\Column(type: 'text')]
    private string $title = '';

    /** JSONB: NAMO model system classification (class, species, cell type, vendor, …) */
    #[ORM\Column(type: 'json')]
    private array $modelSystem = [];

    /** JSONB: concentrations, duration, replicates, reference compounds, … */
    #[ORM\Column(type: 'json')]
    private array $experimentalDesign = [];

    /** JSONB: endpoints, instrument, software, … */
    #[ORM\Column(type: 'json')]
    private array $assayMetadata = [];

    /** JSONB: TC50, NOAEL, safety multiples, key numerical results */
    #[ORM\Column(type: 'json')]
    private array $dataOutputs = [];

    /** JSONB: study director, facility, ELN references, SOP IDs, git hashes */
    #[ORM\Column(type: 'json')]
    private array $provenance = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'study', targetEntity: EvidenceItem::class, cascade: ['persist', 'remove'])]
    private Collection $evidenceItems;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->evidenceItems = new ArrayCollection();
    }

    public function getId(): Ulid { return $this->id; }
    public function getStudyId(): string { return $this->studyId; }
    public function setStudyId(string $v): static { $this->studyId = $v; return $this; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getContextOfUse(): ContextOfUseCard { return $this->contextOfUse; }
    public function setContextOfUse(ContextOfUseCard $v): static { $this->contextOfUse = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getModelSystem(): array { return $this->modelSystem; }
    public function setModelSystem(array $v): static { $this->modelSystem = $v; return $this; }
    public function getExperimentalDesign(): array { return $this->experimentalDesign; }
    public function setExperimentalDesign(array $v): static { $this->experimentalDesign = $v; return $this; }
    public function getAssayMetadata(): array { return $this->assayMetadata; }
    public function setAssayMetadata(array $v): static { $this->assayMetadata = $v; return $this; }
    public function getDataOutputs(): array { return $this->dataOutputs; }
    public function setDataOutputs(array $v): static { $this->dataOutputs = $v; return $this; }
    public function getProvenance(): array { return $this->provenance; }
    public function setProvenance(array $v): static { $this->provenance = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getEvidenceItems(): Collection { return $this->evidenceItems; }
}
