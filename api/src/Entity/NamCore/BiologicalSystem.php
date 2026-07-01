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
 * NAM-CORE BiologicalSystem — the test system (organoid, organ-on-chip, 2D/3D
 * culture, cell line, QSAR/PBPK model, …) exercised by a NAM study.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_biological_system')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'BiologicalSystem',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class BiologicalSystem
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    /** organoid | organ_on_chip | tissue_on_chip | 2d_culture | 3d_culture | co_culture | cell_line | qsar | pbpk | ... */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $modelSystemType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $speciesLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $speciesOntologyIri = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $anatomyLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $anatomyOntologyIri = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $cellTypeLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $cellTypeOntologyIri = null;

    #[ORM\ManyToOne(targetEntity: CellSource::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?CellSource $cellSource = null;

    /** Required for iPSC-derived systems (SHACL rule). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $differentiationProtocol = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getModelSystemType(): string { return $this->modelSystemType; }
    public function setModelSystemType(string $v): static { $this->modelSystemType = $v; return $this; }
    public function getSpeciesLabel(): ?string { return $this->speciesLabel; }
    public function setSpeciesLabel(?string $v): static { $this->speciesLabel = $v; return $this; }
    public function getSpeciesOntologyIri(): ?string { return $this->speciesOntologyIri; }
    public function setSpeciesOntologyIri(?string $v): static { $this->speciesOntologyIri = $v; return $this; }
    public function getAnatomyLabel(): ?string { return $this->anatomyLabel; }
    public function setAnatomyLabel(?string $v): static { $this->anatomyLabel = $v; return $this; }
    public function getAnatomyOntologyIri(): ?string { return $this->anatomyOntologyIri; }
    public function setAnatomyOntologyIri(?string $v): static { $this->anatomyOntologyIri = $v; return $this; }
    public function getCellTypeLabel(): ?string { return $this->cellTypeLabel; }
    public function setCellTypeLabel(?string $v): static { $this->cellTypeLabel = $v; return $this; }
    public function getCellTypeOntologyIri(): ?string { return $this->cellTypeOntologyIri; }
    public function setCellTypeOntologyIri(?string $v): static { $this->cellTypeOntologyIri = $v; return $this; }
    public function getCellSource(): ?CellSource { return $this->cellSource; }
    public function setCellSource(?CellSource $v): static { $this->cellSource = $v; return $this; }
    public function getDifferentiationProtocol(): ?string { return $this->differentiationProtocol; }
    public function setDifferentiationProtocol(?string $v): static { $this->differentiationProtocol = $v; return $this; }
}
