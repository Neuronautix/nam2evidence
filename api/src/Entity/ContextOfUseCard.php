<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ContextOfUseCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Context of Use Card — the central artefact declaring the regulatory question,
 * intended use, biological domain, limitations, and confidence level for a NAM.
 */
#[ORM\Entity(repositoryClass: ContextOfUseCardRepository::class)]
#[ORM\Table(name: 'context_of_use_cards')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class ContextOfUseCard
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['read'])]
    private Ulid $id;

    /** Human-readable COU identifier, e.g. COU-HEP-001 */
    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $couId = '';

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'contextOfUseCards')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read', 'write'])]
    private Project $project;

    /** NAMO model-system class: Organoid | OrganOnChip | QSARModel | CellBasedAssay | … */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $namType = '';

    /** The specific regulatory / scientific question the NAM is deployed to answer */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $regulatoryQuestion = '';

    /** IND-enabling | pre_IND | phase_I | … */
    #[ORM\Column(length: 60)]
    #[Groups(['read', 'write'])]
    private string $drugDevelopmentStage = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $intendedUse = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $decisionSupported = '';

    #[ORM\Column(length: 255)]
    #[Groups(['read', 'write'])]
    private string $biologicalDomain = '';

    #[ORM\Column(length: 255)]
    #[Groups(['read', 'write'])]
    private string $endpointClass = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $populationRelevance = null;

    /** JSONB array of known model limitations */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $limitations = [];

    /** JSONB array of pre-specified acceptance criteria */
    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $acceptanceCriteria = [];

    /** exploratory | supportive | decision_informing | potentially_pivotal */
    #[ORM\Column(length: 30)]
    #[Groups(['read', 'write'])]
    private string $regulatoryConfidenceLevel = 'exploratory';

    #[ORM\Column(length: 20)]
    #[Groups(['read', 'write'])]
    private string $version = '1.0';

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['read'])]
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
    public function getCouId(): string { return $this->couId; }
    public function setCouId(string $couId): static { $this->couId = $couId; return $this; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $project): static { $this->project = $project; return $this; }
    public function getNamType(): string { return $this->namType; }
    public function setNamType(string $namType): static { $this->namType = $namType; return $this; }
    public function getRegulatoryQuestion(): string { return $this->regulatoryQuestion; }
    public function setRegulatoryQuestion(string $q): static { $this->regulatoryQuestion = $q; return $this; }
    public function getDrugDevelopmentStage(): string { return $this->drugDevelopmentStage; }
    public function setDrugDevelopmentStage(string $s): static { $this->drugDevelopmentStage = $s; return $this; }
    public function getIntendedUse(): string { return $this->intendedUse; }
    public function setIntendedUse(string $v): static { $this->intendedUse = $v; return $this; }
    public function getDecisionSupported(): string { return $this->decisionSupported; }
    public function setDecisionSupported(string $v): static { $this->decisionSupported = $v; return $this; }
    public function getBiologicalDomain(): string { return $this->biologicalDomain; }
    public function setBiologicalDomain(string $v): static { $this->biologicalDomain = $v; return $this; }
    public function getEndpointClass(): string { return $this->endpointClass; }
    public function setEndpointClass(string $v): static { $this->endpointClass = $v; return $this; }
    public function getPopulationRelevance(): ?string { return $this->populationRelevance; }
    public function setPopulationRelevance(?string $v): static { $this->populationRelevance = $v; return $this; }
    public function getLimitations(): array { return $this->limitations; }
    public function setLimitations(array $v): static { $this->limitations = $v; return $this; }
    public function getAcceptanceCriteria(): array { return $this->acceptanceCriteria; }
    public function setAcceptanceCriteria(array $v): static { $this->acceptanceCriteria = $v; return $this; }
    public function getRegulatoryConfidenceLevel(): string { return $this->regulatoryConfidenceLevel; }
    public function setRegulatoryConfidenceLevel(string $v): static { $this->regulatoryConfidenceLevel = $v; return $this; }
    public function getVersion(): string { return $this->version; }
    public function setVersion(string $v): static { $this->version = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
