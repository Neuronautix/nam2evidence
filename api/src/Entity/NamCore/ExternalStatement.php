<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An immutable capture of a statement published by an external evidence
 * source — first of all a TIB Knowledge Loom statement (DOI-identified, with
 * declared data/code links).
 *
 * A captured statement is *external, curated evidence*. It is never a
 * nam2evidence measurement, never a validation-evidence row, and it never
 * satisfies the "decision-informing claims need validation evidence" rule on
 * its own. It can only be linked to project records through an
 * ExternalStatementLink that a named human approves.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_external_statement',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_external_statement_identity', columns: ['project_id', 'statement_uri', 'source_version']),
    ]
)]
#[ORM\Index(name: 'idx_external_statement_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_external_statement_source', columns: ['source_system'])]
#[ORM\HasLifecycleCallbacks]
class ExternalStatement
{
    public const SOURCE_LOOM = 'loom';

    public const KIND_AUTHOR_CLAIM = 'author_claim';
    public const KIND_CURATED_STATEMENT = 'curated_statement';
    public const KIND_ANALYTICAL_FINDING = 'analytical_finding';

    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_RESTRICTED = 'restricted';
    public const ACCESS_UNKNOWN = 'unknown';

    public const CAPTURE_UNVERIFIED = 'captured_unverified';
    public const CAPTURE_VERIFIED = 'captured_verified';
    public const CAPTURE_SUPERSEDED = 'superseded';
    public const CAPTURE_WITHDRAWN = 'withdrawn';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    /** loom | orkg | diggypaper | other */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $sourceSystem = self::SOURCE_LOOM;

    /** Statement DOI or URI as issued by the source (e.g. a Loom statement DOI). */
    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private string $statementUri = '';

    /** The source record (the work) the statement belongs to. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $recordUri = null;

    /** DOI of the underlying publication, when the source declares one. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalWorkDoi = null;

    /** Source-side version / capture identifier. Part of the identity. */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $sourceVersion = '1';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $statementText = '';

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [self::KIND_AUTHOR_CLAIM, self::KIND_CURATED_STATEMENT, self::KIND_ANALYTICAL_FINDING])]
    private string $statementKind = self::KIND_CURATED_STATEMENT;

    #[ORM\Column(type: 'json')]
    private array $authors = [];

    /** @var list<array<string,mixed>> {uri, label, checksum?, licence?} */
    #[ORM\Column(type: 'json')]
    private array $datasetLinks = [];

    /** @var list<array<string,mixed>> */
    #[ORM\Column(type: 'json')]
    private array $codeLinks = [];

    /** Population/model, exposure, comparator, outcome, measurement/unit, time window, design. Never guessed. */
    #[ORM\Column(type: 'json')]
    private array $studyContext = [];

    /** Whatever the source declares; no derived score. */
    #[ORM\Column(type: 'json')]
    private array $uncertainty = [];

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $licence = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::ACCESS_PUBLIC, self::ACCESS_RESTRICTED, self::ACCESS_UNKNOWN])]
    private string $accessScope = self::ACCESS_UNKNOWN;

    /** Exact captured payload for this statement. */
    #[ORM\Column(type: 'json')]
    private array $rawPayload = [];

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private string $payloadSha256 = '';

    #[ORM\Column]
    private \DateTimeImmutable $capturedAt;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $capturedBy = 'system';

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::CAPTURE_UNVERIFIED, self::CAPTURE_VERIFIED, self::CAPTURE_SUPERSEDED, self::CAPTURE_WITHDRAWN])]
    private string $captureStatus = self::CAPTURE_UNVERIFIED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verifiedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->capturedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getSourceSystem(): string { return $this->sourceSystem; }
    public function setSourceSystem(string $v): static { $this->sourceSystem = $v; return $this; }
    public function getStatementUri(): string { return $this->statementUri; }
    public function setStatementUri(string $v): static { $this->statementUri = $v; return $this; }
    public function getRecordUri(): ?string { return $this->recordUri; }
    public function setRecordUri(?string $v): static { $this->recordUri = $v; return $this; }
    public function getOriginalWorkDoi(): ?string { return $this->originalWorkDoi; }
    public function setOriginalWorkDoi(?string $v): static { $this->originalWorkDoi = $v; return $this; }
    public function getSourceVersion(): string { return $this->sourceVersion; }
    public function setSourceVersion(string $v): static { $this->sourceVersion = $v; return $this; }
    public function getStatementText(): string { return $this->statementText; }
    public function setStatementText(string $v): static { $this->statementText = $v; return $this; }
    public function getStatementKind(): string { return $this->statementKind; }
    public function setStatementKind(string $v): static { $this->statementKind = $v; return $this; }
    public function getAuthors(): array { return $this->authors; }
    public function setAuthors(array $v): static { $this->authors = $v; return $this; }
    public function getDatasetLinks(): array { return $this->datasetLinks; }
    public function setDatasetLinks(array $v): static { $this->datasetLinks = $v; return $this; }
    public function getCodeLinks(): array { return $this->codeLinks; }
    public function setCodeLinks(array $v): static { $this->codeLinks = $v; return $this; }
    public function getStudyContext(): array { return $this->studyContext; }
    public function setStudyContext(array $v): static { $this->studyContext = $v; return $this; }
    public function getUncertainty(): array { return $this->uncertainty; }
    public function setUncertainty(array $v): static { $this->uncertainty = $v; return $this; }
    public function getLicence(): ?string { return $this->licence; }
    public function setLicence(?string $v): static { $this->licence = $v; return $this; }
    public function getAccessScope(): string { return $this->accessScope; }
    public function setAccessScope(string $v): static { $this->accessScope = $v; return $this; }
    public function getRawPayload(): array { return $this->rawPayload; }
    public function setRawPayload(array $v): static { $this->rawPayload = $v; return $this; }
    public function getPayloadSha256(): string { return $this->payloadSha256; }
    public function setPayloadSha256(string $v): static { $this->payloadSha256 = $v; return $this; }
    public function getCapturedAt(): \DateTimeImmutable { return $this->capturedAt; }
    public function setCapturedAt(\DateTimeImmutable $v): static { $this->capturedAt = $v; return $this; }
    public function getCapturedBy(): string { return $this->capturedBy; }
    public function setCapturedBy(string $v): static { $this->capturedBy = $v; return $this; }
    public function getCaptureStatus(): string { return $this->captureStatus; }
    public function setCaptureStatus(string $v): static { $this->captureStatus = $v; return $this; }
    public function getVerifiedBy(): ?string { return $this->verifiedBy; }
    public function setVerifiedBy(?string $v): static { $this->verifiedBy = $v; return $this; }
    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeImmutable $v): static { $this->verifiedAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** A statement may be cited as *supporting* evidence only when it is public and its capture was checked by a human. */
    public function isEligibleForSupport(): bool
    {
        return $this->accessScope === self::ACCESS_PUBLIC && $this->captureStatus === self::CAPTURE_VERIFIED;
    }

    /** Restricted or unknown-scope statements never leave the project in an export. */
    public function isExportable(): bool
    {
        return $this->accessScope === self::ACCESS_PUBLIC && $this->captureStatus !== self::CAPTURE_WITHDRAWN;
    }
}
