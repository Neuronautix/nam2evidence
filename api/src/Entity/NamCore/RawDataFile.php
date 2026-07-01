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
 * NAM-CORE RawDataFile — a raw data file ingested from an ELN/LIMS/instrument.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_raw_data_file')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'RawDataFile',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class RawDataFile
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $fileName = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $checksum = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $checksumAlgorithm = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?\DateTimeImmutable $uploadDate = null;

    /** eln | lims | instrument_export | manual_upload */
    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $sourceSystem = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $instrumentVersion = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $mediaType = null;

    /** Doctrine bigint maps to string in PHP. */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $byteSize = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $v): static { $this->fileName = $v; return $this; }
    public function getChecksum(): ?string { return $this->checksum; }
    public function setChecksum(?string $v): static { $this->checksum = $v; return $this; }
    public function getChecksumAlgorithm(): ?string { return $this->checksumAlgorithm; }
    public function setChecksumAlgorithm(?string $v): static { $this->checksumAlgorithm = $v; return $this; }
    public function getUploadDate(): ?\DateTimeImmutable { return $this->uploadDate; }
    public function setUploadDate(?\DateTimeImmutable $v): static { $this->uploadDate = $v; return $this; }
    public function getSourceSystem(): ?string { return $this->sourceSystem; }
    public function setSourceSystem(?string $v): static { $this->sourceSystem = $v; return $this; }
    public function getInstrumentVersion(): ?string { return $this->instrumentVersion; }
    public function setInstrumentVersion(?string $v): static { $this->instrumentVersion = $v; return $this; }
    public function getMediaType(): ?string { return $this->mediaType; }
    public function setMediaType(?string $v): static { $this->mediaType = $v; return $this; }
    public function getByteSize(): ?string { return $this->byteSize; }
    public function setByteSize(?string $v): static { $this->byteSize = $v; return $this; }
}
