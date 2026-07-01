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
 * NAM-CORE EndpointMeasurement — the canonical, SEND-like tabular core of a NAM
 * study. One row = one measured value for one endpoint, on one sample, under one
 * exposure, at one timepoint. Foreign keys are explicit and queryable; only the
 * `extensions` bag (from the trait) is free-form.
 *
 * Note: `value` (numeric) and `valueRaw` (verbatim string as imported) are kept
 * side by side so non-numeric imports can be captured, surfaced by validation,
 * and corrected without data loss.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_endpoint_measurement')]
#[ORM\Index(name: 'idx_epm_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_epm_endpoint', columns: ['endpoint_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'EndpointMeasurement',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class EndpointMeasurement
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: NAMStudy::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?NAMStudy $study = null;

    #[ORM\ManyToOne(targetEntity: Assay::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Assay $assay = null;

    #[ORM\ManyToOne(targetEntity: Sample::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Sample $sample = null;

    #[ORM\ManyToOne(targetEntity: BiologicalSystem::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?BiologicalSystem $biologicalSystem = null;

    #[ORM\ManyToOne(targetEntity: Exposure::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Exposure $exposure = null;

    #[ORM\ManyToOne(targetEntity: Donor::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Donor $donor = null;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Device $device = null;

    #[ORM\ManyToOne(targetEntity: RawDataFile::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?RawDataFile $rawDataFile = null;

    #[ORM\ManyToOne(targetEntity: ProvenanceActivity::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?ProvenanceActivity $analysisActivity = null;

    /** Stable endpoint key, e.g. "atp_viability". */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $endpointId = '';

    #[ORM\Column(length: 255)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $endpointLabel = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $endpointOntologyIri = null;

    /** Parsed numeric value (null when the raw value is non-numeric). */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $value = null;

    /** Verbatim value as imported, preserved for audit. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $valueRaw = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $unit = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $unitOntologyIri = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?float $timepointValue = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $timepointUnit = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $replicateId = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $batchId = null;

    /** pending | pass | fail | warn */
    #[ORM\Column(length: 20)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $qcStatus = 'pending';

    /** included | excluded */
    #[ORM\Column(length: 20)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $exclusionStatus = 'included';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $exclusionReason = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getStudy(): ?NAMStudy { return $this->study; }
    public function setStudy(?NAMStudy $v): static { $this->study = $v; return $this; }
    public function getAssay(): ?Assay { return $this->assay; }
    public function setAssay(?Assay $v): static { $this->assay = $v; return $this; }
    public function getSample(): ?Sample { return $this->sample; }
    public function setSample(?Sample $v): static { $this->sample = $v; return $this; }
    public function getBiologicalSystem(): ?BiologicalSystem { return $this->biologicalSystem; }
    public function setBiologicalSystem(?BiologicalSystem $v): static { $this->biologicalSystem = $v; return $this; }
    public function getExposure(): ?Exposure { return $this->exposure; }
    public function setExposure(?Exposure $v): static { $this->exposure = $v; return $this; }
    public function getDonor(): ?Donor { return $this->donor; }
    public function setDonor(?Donor $v): static { $this->donor = $v; return $this; }
    public function getDevice(): ?Device { return $this->device; }
    public function setDevice(?Device $v): static { $this->device = $v; return $this; }
    public function getRawDataFile(): ?RawDataFile { return $this->rawDataFile; }
    public function setRawDataFile(?RawDataFile $v): static { $this->rawDataFile = $v; return $this; }
    public function getAnalysisActivity(): ?ProvenanceActivity { return $this->analysisActivity; }
    public function setAnalysisActivity(?ProvenanceActivity $v): static { $this->analysisActivity = $v; return $this; }
    public function getEndpointId(): string { return $this->endpointId; }
    public function setEndpointId(string $v): static { $this->endpointId = $v; return $this; }
    public function getEndpointLabel(): string { return $this->endpointLabel; }
    public function setEndpointLabel(string $v): static { $this->endpointLabel = $v; return $this; }
    public function getEndpointOntologyIri(): ?string { return $this->endpointOntologyIri; }
    public function setEndpointOntologyIri(?string $v): static { $this->endpointOntologyIri = $v; return $this; }
    public function getValue(): ?float { return $this->value; }
    public function setValue(?float $v): static { $this->value = $v; return $this; }
    public function getValueRaw(): ?string { return $this->valueRaw; }
    public function setValueRaw(?string $v): static { $this->valueRaw = $v; return $this; }
    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(?string $v): static { $this->unit = $v; return $this; }
    public function getUnitOntologyIri(): ?string { return $this->unitOntologyIri; }
    public function setUnitOntologyIri(?string $v): static { $this->unitOntologyIri = $v; return $this; }
    public function getTimepointValue(): ?float { return $this->timepointValue; }
    public function setTimepointValue(?float $v): static { $this->timepointValue = $v; return $this; }
    public function getTimepointUnit(): ?string { return $this->timepointUnit; }
    public function setTimepointUnit(?string $v): static { $this->timepointUnit = $v; return $this; }
    public function getReplicateId(): ?string { return $this->replicateId; }
    public function setReplicateId(?string $v): static { $this->replicateId = $v; return $this; }
    public function getBatchId(): ?string { return $this->batchId; }
    public function setBatchId(?string $v): static { $this->batchId = $v; return $this; }
    public function getQcStatus(): string { return $this->qcStatus; }
    public function setQcStatus(string $v): static { $this->qcStatus = $v; return $this; }
    public function getExclusionStatus(): string { return $this->exclusionStatus; }
    public function setExclusionStatus(string $v): static { $this->exclusionStatus = $v; return $this; }
    public function getExclusionReason(): ?string { return $this->exclusionReason; }
    public function setExclusionReason(?string $v): static { $this->exclusionReason = $v; return $this; }
}
