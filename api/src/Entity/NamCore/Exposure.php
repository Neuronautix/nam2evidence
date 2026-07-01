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
 * NAM-CORE Exposure — a test-article treatment (dose/concentration/timepoint).
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_exposure')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Exposure',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class Exposure
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $testArticle = '';

    /** ChEBI ontology IRI. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $testArticleOntologyIri = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $concentrationValue = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $concentrationUnit = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $concentrationUnitOntologyIri = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $timepointValue = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $timepointUnit = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $vehicle = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getTestArticle(): string { return $this->testArticle; }
    public function setTestArticle(string $v): static { $this->testArticle = $v; return $this; }
    public function getTestArticleOntologyIri(): ?string { return $this->testArticleOntologyIri; }
    public function setTestArticleOntologyIri(?string $v): static { $this->testArticleOntologyIri = $v; return $this; }
    public function getConcentrationValue(): ?float { return $this->concentrationValue; }
    public function setConcentrationValue(?float $v): static { $this->concentrationValue = $v; return $this; }
    public function getConcentrationUnit(): ?string { return $this->concentrationUnit; }
    public function setConcentrationUnit(?string $v): static { $this->concentrationUnit = $v; return $this; }
    public function getConcentrationUnitOntologyIri(): ?string { return $this->concentrationUnitOntologyIri; }
    public function setConcentrationUnitOntologyIri(?string $v): static { $this->concentrationUnitOntologyIri = $v; return $this; }
    public function getTimepointValue(): ?float { return $this->timepointValue; }
    public function setTimepointValue(?float $v): static { $this->timepointValue = $v; return $this; }
    public function getTimepointUnit(): ?string { return $this->timepointUnit; }
    public function setTimepointUnit(?string $v): static { $this->timepointUnit = $v; return $this; }
    public function getVehicle(): ?string { return $this->vehicle; }
    public function setVehicle(?string $v): static { $this->vehicle = $v; return $this; }
}
