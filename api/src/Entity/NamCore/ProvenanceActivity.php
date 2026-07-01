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
 * NAM-CORE ProvenanceActivity — a PROV-style activity (ingestion, processing,
 * analysis, export) performed by software/agents on the data.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_provenance_activity')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'ProvenanceActivity',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class ProvenanceActivity
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    /** ingestion | processing | analysis | export */
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $activityType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $softwareName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $softwareVersion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $scriptReference = null;

    #[ORM\ManyToOne(targetEntity: AnalysisScript::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?AnalysisScript $analysisScript = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $agentName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $agentRole = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?\DateTimeImmutable $endedAt = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getActivityType(): string { return $this->activityType; }
    public function setActivityType(string $v): static { $this->activityType = $v; return $this; }
    public function getSoftwareName(): ?string { return $this->softwareName; }
    public function setSoftwareName(?string $v): static { $this->softwareName = $v; return $this; }
    public function getSoftwareVersion(): ?string { return $this->softwareVersion; }
    public function setSoftwareVersion(?string $v): static { $this->softwareVersion = $v; return $this; }
    public function getScriptReference(): ?string { return $this->scriptReference; }
    public function setScriptReference(?string $v): static { $this->scriptReference = $v; return $this; }
    public function getAnalysisScript(): ?AnalysisScript { return $this->analysisScript; }
    public function setAnalysisScript(?AnalysisScript $v): static { $this->analysisScript = $v; return $this; }
    public function getAgentName(): ?string { return $this->agentName; }
    public function setAgentName(?string $v): static { $this->agentName = $v; return $this; }
    public function getAgentRole(): ?string { return $this->agentRole; }
    public function setAgentRole(?string $v): static { $this->agentRole = $v; return $this; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $v): static { $this->startedAt = $v; return $this; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(?\DateTimeImmutable $v): static { $this->endedAt = $v; return $this; }
}
