<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reusable NAM method definition.
 *
 * A method is intentionally separated from the project, Context of Use, study,
 * and regulatory/scientific assessment. The same method can therefore be used
 * in several CoUs and studies without conflating method identity with evidence
 * or acceptance status.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_nam_method',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_nam_method_project_method', columns: ['project_id', 'method_id'])
    ]
)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'NAMMethod',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class NAMMethod
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: SourceProject::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?SourceProject $sourceProject = null;

    /** Stable business identifier, e.g. METHOD-LIVERCHIP-001 or an imported registry ID. */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $methodId = '';

    /** organoid | organ_on_chip | cell_based_assay | qsar | pbpk | defined_approach | ... */
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $methodType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $developer = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $methodVersion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $technicalDescription = null;

    /** development | characterization | validation | qualified | accepted | retired | unknown */
    #[ORM\Column(length: 40)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $maturityStatus = 'unknown';

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $ontologyIri = null;

    /** External registry/database identifiers that identify this method. */
    #[ORM\Column(type: 'json')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private array $externalIdentifiers = [];

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getSourceProject(): ?SourceProject { return $this->sourceProject; }
    public function setSourceProject(?SourceProject $v): static { $this->sourceProject = $v; return $this; }
    public function getMethodId(): string { return $this->methodId; }
    public function setMethodId(string $v): static { $this->methodId = $v; return $this; }
    public function getMethodType(): string { return $this->methodType; }
    public function setMethodType(string $v): static { $this->methodType = $v; return $this; }
    public function getDeveloper(): ?string { return $this->developer; }
    public function setDeveloper(?string $v): static { $this->developer = $v; return $this; }
    public function getMethodVersion(): ?string { return $this->methodVersion; }
    public function setMethodVersion(?string $v): static { $this->methodVersion = $v; return $this; }
    public function getTechnicalDescription(): ?string { return $this->technicalDescription; }
    public function setTechnicalDescription(?string $v): static { $this->technicalDescription = $v; return $this; }
    public function getMaturityStatus(): string { return $this->maturityStatus; }
    public function setMaturityStatus(string $v): static { $this->maturityStatus = $v; return $this; }
    public function getOntologyIri(): ?string { return $this->ontologyIri; }
    public function setOntologyIri(?string $v): static { $this->ontologyIri = $v; return $this; }
    public function getExternalIdentifiers(): array { return $this->externalIdentifiers; }
    public function setExternalIdentifiers(array $v): static { $this->externalIdentifiers = $v; return $this; }
}
