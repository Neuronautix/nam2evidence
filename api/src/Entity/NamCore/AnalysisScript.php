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
 * NAM-CORE AnalysisScript — a versioned analysis/processing script reference.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_analysis_script')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AnalysisScript',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class AnalysisScript
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $repositoryUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $reference = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $language = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $scriptVersion = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getRepositoryUrl(): ?string { return $this->repositoryUrl; }
    public function setRepositoryUrl(?string $v): static { $this->repositoryUrl = $v; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }
    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $v): static { $this->language = $v; return $this; }
    public function getScriptVersion(): ?string { return $this->scriptVersion; }
    public function setScriptVersion(?string $v): static { $this->scriptVersion = $v; return $this; }
}
