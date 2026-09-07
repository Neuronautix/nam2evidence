<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\NamCore\NAMMethod;
use App\Repository\NAMStudyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: NAMStudyRepository::class)]
#[ORM\Table(name: 'nam_studies')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class NAMStudy
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
    private string $studyId = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read', 'write'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: ContextOfUseCard::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read', 'write'])]
    private ContextOfUseCard $contextOfUse;

    /** Optional explicit method identity. Legacy studies may infer it through their CoU. */
    #[ORM\ManyToOne(targetEntity: NAMMethod::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['read', 'write'])]
    private ?NAMMethod $namMethod = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['read', 'write'])]
    private string $title = '';

    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $modelSystem = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $experimentalDesign = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $assayMetadata = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $dataOutputs = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['read', 'write'])]
    private array $provenance = [];

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'study', targetEntity: EvidenceItem::class, cascade: ['persist', 'remove'])]
    private Collection $evidenceItems;

    public function __construct()
    {
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
    public function getNamMethod(): ?NAMMethod { return $this->namMethod; }
    public function setNamMethod(?NAMMethod $v): static { $this->namMethod = $v; return $this; }
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

    #[Assert\Callback]
    public function validateRelationshipConsistency(ExecutionContextInterface $context): void
    {
        if (isset($this->project, $this->contextOfUse)
            && $this->contextOfUse->getProject()->getId()->toRfc4122() !== $this->project->getId()->toRfc4122()) {
            $context->buildViolation('NAMStudy contextOfUse must belong to the same project as the study.')
                ->atPath('contextOfUse')->addViolation();
        }

        if (isset($this->project) && $this->namMethod !== null
            && $this->namMethod->getProject()->getId()->toRfc4122() !== $this->project->getId()->toRfc4122()) {
            $context->buildViolation('NAMStudy namMethod must belong to the same project as the study.')
                ->atPath('namMethod')->addViolation();
        }

        if (isset($this->contextOfUse) && $this->namMethod !== null) {
            $couMethod = $this->contextOfUse->getNamMethod();
            if ($couMethod === null) {
                $context->buildViolation('NAMStudy namMethod cannot be set when contextOfUse has no NAM method link.')
                    ->atPath('namMethod')->addViolation();
            } elseif ($couMethod->getId()->toRfc4122() !== $this->namMethod->getId()->toRfc4122()) {
                $context->buildViolation('NAMStudy namMethod must match the NAM method linked to contextOfUse.')
                    ->atPath('namMethod')->addViolation();
            }
        }
    }
}
