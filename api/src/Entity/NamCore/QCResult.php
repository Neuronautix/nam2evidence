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
 * NAM-CORE QCResult — a quality-control metric evaluated against a threshold.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_qc_result')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'QCResult',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class QCResult
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $metricName = '';

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $metricValue = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $threshold = null;

    /** pass | fail | warn */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $passFail = null;

    #[ORM\ManyToOne(targetEntity: Assay::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Assay $assay = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getMetricName(): string { return $this->metricName; }
    public function setMetricName(string $v): static { $this->metricName = $v; return $this; }
    public function getMetricValue(): ?float { return $this->metricValue; }
    public function setMetricValue(?float $v): static { $this->metricValue = $v; return $this; }
    public function getThreshold(): ?string { return $this->threshold; }
    public function setThreshold(?string $v): static { $this->threshold = $v; return $this; }
    public function getPassFail(): ?string { return $this->passFail; }
    public function setPassFail(?string $v): static { $this->passFail = $v; return $this; }
    public function getAssay(): ?Assay { return $this->assay; }
    public function setAssay(?Assay $v): static { $this->assay = $v; return $this; }
}
