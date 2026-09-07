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
 * Provenance record for an external NAM project, programme, database entry, or
 * consortium from which a normalized nam2evidence record was derived.
 *
 * SourceProject deliberately does not imply scientific or regulatory approval;
 * it only captures where the integrated record came from.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_source_project')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SourceProject',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class SourceProject
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    /** internal | fda | oecd | eurl_ecvam | db_alm | academic | consortium | industry | other */
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $sourceType = 'other';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $organisation = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $sourceVersion = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?\DateTimeImmutable $sourceUpdatedAt = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $v): static { $this->sourceType = $v; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $v): static { $this->externalId = $v; return $this; }
    public function getOrganisation(): ?string { return $this->organisation; }
    public function setOrganisation(?string $v): static { $this->organisation = $v; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): static { $this->sourceUrl = $v; return $this; }
    public function getSourceVersion(): ?string { return $this->sourceVersion; }
    public function setSourceVersion(?string $v): static { $this->sourceVersion = $v; return $this; }
    public function getSourceUpdatedAt(): ?\DateTimeImmutable { return $this->sourceUpdatedAt; }
    public function setSourceUpdatedAt(?\DateTimeImmutable $v): static { $this->sourceUpdatedAt = $v; return $this; }
}
