<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Repository\ECTDMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * Maps a NAM study report or claim summary to a specific eCTD Module 4 section.
 * e.g. study report → 4.2.3.7.3 (Other In Vitro Studies)
 *      WoE summary  → 2.6.6    (Toxicology Written Summary)
 */
#[ORM\Entity(repositoryClass: ECTDMappingRepository::class)]
#[ORM\Table(name: 'ectd_mappings')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()]
)]
class ECTDMapping
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    private string $mappingId = '';

    #[ORM\ManyToOne(targetEntity: NAMStudy::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?NAMStudy $study = null;

    #[ORM\ManyToOne(targetEntity: ClaimNode::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ClaimNode $claim = null;

    #[ORM\Column(length: 255)]
    private string $evidenceType = '';

    /** eCTD section code, e.g. "4.2.3.7.3" */
    #[ORM\Column(length: 30)]
    private string $ectdSection = '';

    #[ORM\Column(length: 255)]
    private string $ectdTitle = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $justification = null;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid { return $this->id; }
    public function getMappingId(): string { return $this->mappingId; }
    public function setMappingId(string $v): static { $this->mappingId = $v; return $this; }
    public function getStudy(): ?NAMStudy { return $this->study; }
    public function setStudy(?NAMStudy $v): static { $this->study = $v; return $this; }
    public function getClaim(): ?ClaimNode { return $this->claim; }
    public function setClaim(?ClaimNode $v): static { $this->claim = $v; return $this; }
    public function getEvidenceType(): string { return $this->evidenceType; }
    public function setEvidenceType(string $v): static { $this->evidenceType = $v; return $this; }
    public function getEctdSection(): string { return $this->ectdSection; }
    public function setEctdSection(string $v): static { $this->ectdSection = $v; return $this; }
    public function getEctdTitle(): string { return $this->ectdTitle; }
    public function setEctdTitle(string $v): static { $this->ectdTitle = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getJustification(): ?string { return $this->justification; }
    public function setJustification(?string $v): static { $this->justification = $v; return $this; }
}
