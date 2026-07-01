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
 * NAM-CORE Donor — the biological donor from which cells/tissues originate.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_donor')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Donor',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class Donor
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $donorCode = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $speciesLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $speciesOntologyIri = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $sex = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $ageValue = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $ageUnit = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $passageNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $healthStatus = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getDonorCode(): string { return $this->donorCode; }
    public function setDonorCode(string $v): static { $this->donorCode = $v; return $this; }
    public function getSpeciesLabel(): ?string { return $this->speciesLabel; }
    public function setSpeciesLabel(?string $v): static { $this->speciesLabel = $v; return $this; }
    public function getSpeciesOntologyIri(): ?string { return $this->speciesOntologyIri; }
    public function setSpeciesOntologyIri(?string $v): static { $this->speciesOntologyIri = $v; return $this; }
    public function getSex(): ?string { return $this->sex; }
    public function setSex(?string $v): static { $this->sex = $v; return $this; }
    public function getAgeValue(): ?float { return $this->ageValue; }
    public function setAgeValue(?float $v): static { $this->ageValue = $v; return $this; }
    public function getAgeUnit(): ?string { return $this->ageUnit; }
    public function setAgeUnit(?string $v): static { $this->ageUnit = $v; return $this; }
    public function getPassageNumber(): ?string { return $this->passageNumber; }
    public function setPassageNumber(?string $v): static { $this->passageNumber = $v; return $this; }
    public function getHealthStatus(): ?string { return $this->healthStatus; }
    public function setHealthStatus(?string $v): static { $this->healthStatus = $v; return $this; }
}
