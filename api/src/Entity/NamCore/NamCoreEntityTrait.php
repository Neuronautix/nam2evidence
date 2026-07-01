<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

/**
 * Shared NAM-CORE v0.1 fields.
 *
 * Every canonical NAM-CORE entity carries a stable internal identifier, a
 * human-readable label, an optional description, a schema/record version,
 * a validation status, audit timestamps, and a JSONB `extensions` bag for
 * forward-compatible flexible fields. Core domain fields live on the entities
 * themselves as explicit, queryable, validated columns — `extensions` is only
 * for genuinely open-ended metadata so the model stays modular for future
 * organ-on-chip, omics, imaging and electrophysiology payloads.
 *
 * Classes using this trait must declare `#[ORM\HasLifecycleCallbacks]` and call
 * {@see initNamCore()} from their constructor.
 */
trait NamCoreEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['namcore:read'])]
    private Ulid $id;

    /** Human-readable label. */
    #[ORM\Column(length: 255)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $label = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $description = null;

    /** Record version (semantic-ish). NAM-CORE schema version is a separate constant. */
    #[ORM\Column(length: 20)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $version = '0.1';

    /** unvalidated | valid | warnings | errors */
    #[ORM\Column(length: 20)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $validationStatus = 'unvalidated';

    /** Forward-compatible flexible extension fields (JSONB). */
    #[ORM\Column(type: 'json')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private array $extensions = [];

    #[ORM\Column]
    #[Groups(['namcore:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['namcore:read'])]
    private \DateTimeImmutable $updatedAt;

    protected function initNamCore(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchNamCore(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getVersion(): string { return $this->version; }
    public function setVersion(string $v): static { $this->version = $v; return $this; }

    public function getValidationStatus(): string { return $this->validationStatus; }
    public function setValidationStatus(string $v): static { $this->validationStatus = $v; return $this; }

    public function getExtensions(): array { return $this->extensions; }
    public function setExtensions(array $v): static { $this->extensions = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
