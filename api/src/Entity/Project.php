<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class Project
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['read'])]
    private Ulid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $description = null;

    /**
     * Workspace/program type. A Project is no longer assumed to be a drug dossier:
     * it can represent an imported regulatory record, NAM development programme,
     * academic project, consortium, or other evidence collection.
     */
    #[ORM\Column(length: 40, options: ['default' => 'drug_development'])]
    #[Assert\Choice(choices: [
        'drug_development',
        'method_development',
        'regulatory_reference',
        'academic',
        'consortium',
        'industry',
        'other',
    ])]
    #[Groups(['read', 'write'])]
    private string $projectType = 'drug_development';

    /** Optional legacy/test-article convenience field. Not all NAM projects have a drug. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $drugName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $sponsor = null;

    /** Human-readable source/database/programme name for imported projects. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $sourceName = null;

    /** External project/record identifier in the source system. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['read', 'write'])]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 50)]
    #[Groups(['read', 'write'])]
    private string $reviewStatus = 'pending';

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ContextOfUseCard::class, cascade: ['persist', 'remove'])]
    private Collection $contextOfUseCards;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->contextOfUseCards = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getProjectType(): string { return $this->projectType; }
    public function setProjectType(string $projectType): static { $this->projectType = $projectType; return $this; }
    public function getDrugName(): string { return $this->drugName ?? ''; }
    public function setDrugName(?string $drugName): static { $this->drugName = $drugName; return $this; }
    public function getSponsor(): ?string { return $this->sponsor; }
    public function setSponsor(?string $sponsor): static { $this->sponsor = $sponsor; return $this; }
    public function getSourceName(): ?string { return $this->sourceName; }
    public function setSourceName(?string $sourceName): static { $this->sourceName = $sourceName; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): static { $this->sourceUrl = $sourceUrl; return $this; }
    public function getReviewStatus(): string { return $this->reviewStatus; }
    public function setReviewStatus(string $reviewStatus): static { $this->reviewStatus = $reviewStatus; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getContextOfUseCards(): Collection { return $this->contextOfUseCards; }
}
