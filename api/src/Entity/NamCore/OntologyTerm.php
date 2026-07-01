<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A controlled-vocabulary term from an external ontology (CL, UBERON, ChEBI,
 * OBI, MONDO, NCIT, UCUM/QUDT, NCBITaxon) or an internal NAM vocabulary.
 * Seeded locally — no live internet lookup is required for the POC.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_ontology_term')]
#[ORM\UniqueConstraint(name: 'uq_ontology_curie', columns: ['curie'])]
#[ApiResource(
    shortName: 'OntologyTerm',
    operations: [new GetCollection(), new Post(), new Get()],
    normalizationContext: ['groups' => ['ontology:read']],
    denormalizationContext: ['groups' => ['ontology:write']],
)]
class OntologyTerm
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['ontology:read'])]
    private Ulid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['ontology:read', 'ontology:write'])]
    private string $label = '';

    /** e.g. CL, UBERON, CHEBI, OBI, MONDO, NCIT, UO, UCUM, NCBITaxon, NAM */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Groups(['ontology:read', 'ontology:write'])]
    private string $ontologyPrefix = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['ontology:read', 'ontology:write'])]
    private ?string $iri = null;

    /** Compact URI, e.g. CL:0000182. */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['ontology:read', 'ontology:write'])]
    private string $curie = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ontology:read', 'ontology:write'])]
    private ?string $definition = null;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['ontology:read', 'ontology:write'])]
    private array $synonyms = [];

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['ontology:read', 'ontology:write'])]
    private ?string $source = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['ontology:read', 'ontology:write'])]
    private ?string $termVersion = null;

    #[ORM\Column]
    #[Groups(['ontology:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }
    public function getOntologyPrefix(): string { return $this->ontologyPrefix; }
    public function setOntologyPrefix(string $v): static { $this->ontologyPrefix = $v; return $this; }
    public function getIri(): ?string { return $this->iri; }
    public function setIri(?string $v): static { $this->iri = $v; return $this; }
    public function getCurie(): string { return $this->curie; }
    public function setCurie(string $v): static { $this->curie = $v; return $this; }
    public function getDefinition(): ?string { return $this->definition; }
    public function setDefinition(?string $v): static { $this->definition = $v; return $this; }
    public function getSynonyms(): array { return $this->synonyms; }
    public function setSynonyms(array $v): static { $this->synonyms = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }
    public function getTermVersion(): ?string { return $this->termVersion; }
    public function setTermVersion(?string $v): static { $this->termVersion = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
