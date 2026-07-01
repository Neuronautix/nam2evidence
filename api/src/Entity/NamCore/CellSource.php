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
 * NAM-CORE CellSource — provenance of the cells (iPSC, primary, cell line, …).
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_cell_source')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'CellSource',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class CellSource
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    /** ipsc | primary | cell_line | ... */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $sourceType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $vendor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $catalogNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $lotNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $cellTypeLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $cellTypeOntologyIri = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $differentiationProtocol = null;

    #[ORM\ManyToOne(targetEntity: Donor::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Donor $donor = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $v): static { $this->sourceType = $v; return $this; }
    public function getVendor(): ?string { return $this->vendor; }
    public function setVendor(?string $v): static { $this->vendor = $v; return $this; }
    public function getCatalogNumber(): ?string { return $this->catalogNumber; }
    public function setCatalogNumber(?string $v): static { $this->catalogNumber = $v; return $this; }
    public function getLotNumber(): ?string { return $this->lotNumber; }
    public function setLotNumber(?string $v): static { $this->lotNumber = $v; return $this; }
    public function getCellTypeLabel(): ?string { return $this->cellTypeLabel; }
    public function setCellTypeLabel(?string $v): static { $this->cellTypeLabel = $v; return $this; }
    public function getCellTypeOntologyIri(): ?string { return $this->cellTypeOntologyIri; }
    public function setCellTypeOntologyIri(?string $v): static { $this->cellTypeOntologyIri = $v; return $this; }
    public function getDifferentiationProtocol(): ?string { return $this->differentiationProtocol; }
    public function setDifferentiationProtocol(?string $v): static { $this->differentiationProtocol = $v; return $this; }
    public function getDonor(): ?Donor { return $this->donor; }
    public function setDonor(?Donor $v): static { $this->donor = $v; return $this; }
}
