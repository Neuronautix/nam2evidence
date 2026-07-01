<?php

declare(strict_types=1);

namespace App\Entity\NamCore;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Project;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;

/**
 * Append-only audit event. Records who changed what, when, and why across
 * NAM-CORE and the legacy workspaces so a project carries a defensible trail.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_audit_log')]
#[ORM\Index(name: 'idx_audit_project', columns: ['project_id'])]
#[ApiResource(
    shortName: 'AuditLog',
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['audit:read']],
)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['audit:read'])]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['audit:read'])]
    private ?Project $project = null;

    #[ORM\Column(length: 120)]
    #[Groups(['audit:read'])]
    private string $entityType = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $entityId = null;

    /** create | update | delete | approve | reject | import | export | validate | review_gate */
    #[ORM\Column(length: 40)]
    #[Groups(['audit:read'])]
    private string $action = '';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $oldValue = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $newValue = null;

    #[ORM\Column(length: 120)]
    #[Groups(['audit:read'])]
    private string $userOrRole = 'system';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $reason = null;

    #[ORM\Column]
    #[Groups(['audit:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $v): static { $this->project = $v; return $this; }
    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $v): static { $this->entityType = $v; return $this; }
    public function getEntityId(): ?string { return $this->entityId; }
    public function setEntityId(?string $v): static { $this->entityId = $v; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }
    public function getOldValue(): ?array { return $this->oldValue; }
    public function setOldValue(?array $v): static { $this->oldValue = $v; return $this; }
    public function getNewValue(): ?array { return $this->newValue; }
    public function setNewValue(?array $v): static { $this->newValue = $v; return $this; }
    public function getUserOrRole(): string { return $this->userOrRole; }
    public function setUserOrRole(string $v): static { $this->userOrRole = $v; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): static { $this->reason = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
