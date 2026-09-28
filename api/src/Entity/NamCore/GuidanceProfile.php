<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Immutable-by-convention, versioned interpretation profile for one external
 * guidance/reference document. Profiles are read-only at the HTTP API boundary:
 * curation changes must create a new version rather than silently rewriting the
 * requirements used by an existing assessment.
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'namcore_guidance_profile',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_guidance_profile_key_version', columns: ['profile_key', 'version']),
    ]
)]
#[ORM\Index(name: 'idx_guidance_profile_authority', columns: ['authority'])]
#[ORM\HasLifecycleCallbacks]
class GuidanceProfile
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINAL = 'final';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_OTHER = 'other';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private Ulid $id;

    /** Stable logical identifier shared by successive versions of the profile. */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private string $profileKey = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $authority = '';

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    private string $canonicalSourceUrl = '';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $publicationDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $effectiveDate = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_DRAFT, self::STATUS_FINAL, self::STATUS_WITHDRAWN, self::STATUS_OTHER])]
    private string $status = self::STATUS_OTHER;

    /** Version of this curated interpretation profile, not a regulatory approval state. */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $version = '1.0';

    /** Optional version/date/identifier used by the source document itself. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceVersion = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $jurisdiction = '';

    /** Content hash of the retrieved source artifact when available. */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $sourceHash = null;

    #[ORM\Column]
    private \DateTimeImmutable $retrievedAt;

    /** Retrieval/citation metadata that does not belong in first-class columns. */
    #[ORM\Column(type: 'json')]
    private array $sourceMetadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->retrievedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getProfileKey(): string { return $this->profileKey; }
    public function setProfileKey(string $v): static { $this->profileKey = $v; return $this; }
    public function getAuthority(): string { return $this->authority; }
    public function setAuthority(string $v): static { $this->authority = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getCanonicalSourceUrl(): string { return $this->canonicalSourceUrl; }
    public function setCanonicalSourceUrl(string $v): static { $this->canonicalSourceUrl = $v; return $this; }
    public function getPublicationDate(): ?\DateTimeImmutable { return $this->publicationDate; }
    public function setPublicationDate(?\DateTimeImmutable $v): static { $this->publicationDate = $v; return $this; }
    public function getEffectiveDate(): ?\DateTimeImmutable { return $this->effectiveDate; }
    public function setEffectiveDate(?\DateTimeImmutable $v): static { $this->effectiveDate = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getVersion(): string { return $this->version; }
    public function setVersion(string $v): static { $this->version = $v; return $this; }
    public function getSourceVersion(): ?string { return $this->sourceVersion; }
    public function setSourceVersion(?string $v): static { $this->sourceVersion = $v; return $this; }
    public function getJurisdiction(): string { return $this->jurisdiction; }
    public function setJurisdiction(string $v): static { $this->jurisdiction = $v; return $this; }
    public function getSourceHash(): ?string { return $this->sourceHash; }
    public function setSourceHash(?string $v): static { $this->sourceHash = $v; return $this; }
    public function getRetrievedAt(): \DateTimeImmutable { return $this->retrievedAt; }
    public function setRetrievedAt(\DateTimeImmutable $v): static { $this->retrievedAt = $v; return $this; }
    public function getSourceMetadata(): array { return $this->sourceMetadata; }
    public function setSourceMetadata(array $v): static { $this->sourceMetadata = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
