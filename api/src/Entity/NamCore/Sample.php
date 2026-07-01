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
 * NAM-CORE Sample — a physical/biological sample derived from a test system.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_sample')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Sample',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class Sample
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $sampleCode = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $batchId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $replicateId = null;

    #[ORM\ManyToOne(targetEntity: BiologicalSystem::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?BiologicalSystem $biologicalSystem = null;

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
    public function getSampleCode(): string { return $this->sampleCode; }
    public function setSampleCode(string $v): static { $this->sampleCode = $v; return $this; }
    public function getBatchId(): ?string { return $this->batchId; }
    public function setBatchId(?string $v): static { $this->batchId = $v; return $this; }
    public function getReplicateId(): ?string { return $this->replicateId; }
    public function setReplicateId(?string $v): static { $this->replicateId = $v; return $this; }
    public function getBiologicalSystem(): ?BiologicalSystem { return $this->biologicalSystem; }
    public function setBiologicalSystem(?BiologicalSystem $v): static { $this->biologicalSystem = $v; return $this; }
    public function getDonor(): ?Donor { return $this->donor; }
    public function setDonor(?Donor $v): static { $this->donor = $v; return $this; }
}
