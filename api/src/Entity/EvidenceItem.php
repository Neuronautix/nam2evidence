<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\EvidenceItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single row in the eight-domain validation evidence matrix.
 * Domains: analytical_validity | technical_reproducibility | biological_relevance |
 *          reference_compound_performance | exposure_relevance | data_integrity |
 *          limitation_analysis | regulatory_alignment
 */
#[ORM\Entity(repositoryClass: EvidenceItemRepository::class)]
#[ORM\Table(name: 'evidence_items')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Put()]
)]
class EvidenceItem
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $evidenceId = '';

    #[ORM\ManyToOne(targetEntity: NAMStudy::class, inversedBy: 'evidenceItems')]
    #[ORM\JoinColumn(nullable: false)]
    private NAMStudy $study;

    /** One of the eight validation domains */
    #[ORM\Column(length: 60)]
    #[Assert\Choice(choices: [
        'analytical_validity',
        'technical_reproducibility',
        'biological_relevance',
        'reference_compound_performance',
        'exposure_relevance',
        'data_integrity',
        'limitation_analysis',
        'regulatory_alignment',
    ])]
    private string $domain = '';

    /** The specific evaluation question for this evidence item */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $question = '';

    #[ORM\Column(length: 255)]
    private string $evidenceType = '';

    /** met | partial | not_met | not_applicable */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['met', 'partial', 'not_met', 'not_applicable'])]
    private string $status = 'not_applicable';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $supportingData = null;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid { return $this->id; }
    public function getEvidenceId(): string { return $this->evidenceId; }
    public function setEvidenceId(string $v): static { $this->evidenceId = $v; return $this; }
    public function getStudy(): NAMStudy { return $this->study; }
    public function setStudy(NAMStudy $v): static { $this->study = $v; return $this; }
    public function getDomain(): string { return $this->domain; }
    public function setDomain(string $v): static { $this->domain = $v; return $this; }
    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $v): static { $this->question = $v; return $this; }
    public function getEvidenceType(): string { return $this->evidenceType; }
    public function setEvidenceType(string $v): static { $this->evidenceType = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getSupportingData(): ?string { return $this->supportingData; }
    public function setSupportingData(?string $v): static { $this->supportingData = $v; return $this; }
}
