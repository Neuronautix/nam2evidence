<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NAM-CORE Assay — a measurement/experimental technique applied in a NAM study.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_assay')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Assay',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class Assay
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $assayType = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $method = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $readout = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $technologyLabel = null;

    /** OBI ontology IRI. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $technologyOntologyIri = null;

    #[ORM\ManyToOne(targetEntity: NAMStudy::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?NAMStudy $namStudy = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getAssayType(): string { return $this->assayType; }
    public function setAssayType(string $v): static { $this->assayType = $v; return $this; }
    public function getMethod(): ?string { return $this->method; }
    public function setMethod(?string $v): static { $this->method = $v; return $this; }
    public function getReadout(): ?string { return $this->readout; }
    public function setReadout(?string $v): static { $this->readout = $v; return $this; }
    public function getTechnologyLabel(): ?string { return $this->technologyLabel; }
    public function setTechnologyLabel(?string $v): static { $this->technologyLabel = $v; return $this; }
    public function getTechnologyOntologyIri(): ?string { return $this->technologyOntologyIri; }
    public function setTechnologyOntologyIri(?string $v): static { $this->technologyOntologyIri = $v; return $this; }
    public function getNamStudy(): ?NAMStudy { return $this->namStudy; }
    public function setNamStudy(?NAMStudy $v): static { $this->namStudy = $v; return $this; }
}
