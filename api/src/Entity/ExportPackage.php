<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * Snapshot of a complete evidence package at a point in time.
 * Stores the full denormalised JSON payload for export and archival.
 */
#[ORM\Entity]
#[ORM\Table(name: 'export_packages')]
class ExportPackage
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    #[Groups(['read'])]
    private Ulid $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['read'])]
    private string $packageId = '';

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read'])]
    private Project $project;

    /** Full denormalised package as JSONB */
    #[ORM\Column(type: 'json')]
    #[Groups(['read'])]
    private array $payload = [];

    #[ORM\Column(length: 20)]
    #[Groups(['read'])]
    private string $version = '1.0';

    #[ORM\Column]
    #[Groups(['read'])]
    private \DateTimeImmutable $exportedAt;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->exportedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid { return $this->id; }
    public function getPackageId(): string { return $this->packageId; }
    public function setPackageId(string $v): static { $this->packageId = $v; return $this; }
    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $v): static { $this->payload = $v; return $this; }
    public function getVersion(): string { return $this->version; }
    public function setVersion(string $v): static { $this->version = $v; return $this; }
    public function getExportedAt(): \DateTimeImmutable { return $this->exportedAt; }
}
