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
 * NAM-CORE Device — a concrete instrument instance on a Platform.
 */
#[ORM\Entity]
#[ORM\Table(name: 'namcore_device')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Device',
    operations: [new GetCollection(), new Post(), new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['namcore:read']],
    denormalizationContext: ['groups' => ['namcore:write']],
)]
class Device
{
    use NamCoreEntityTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private Project $project;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Groups(['namcore:read', 'namcore:write'])]
    private string $deviceType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $vendor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $model = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $serialNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?string $firmwareVersion = null;

    #[ORM\ManyToOne(targetEntity: Platform::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['namcore:read', 'namcore:write'])]
    private ?Platform $platform = null;

    public function __construct()
    {
        $this->initNamCore();
    }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $v): static { $this->project = $v; return $this; }
    public function getDeviceType(): string { return $this->deviceType; }
    public function setDeviceType(string $v): static { $this->deviceType = $v; return $this; }
    public function getVendor(): ?string { return $this->vendor; }
    public function setVendor(?string $v): static { $this->vendor = $v; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $v): static { $this->model = $v; return $this; }
    public function getSerialNumber(): ?string { return $this->serialNumber; }
    public function setSerialNumber(?string $v): static { $this->serialNumber = $v; return $this; }
    public function getFirmwareVersion(): ?string { return $this->firmwareVersion; }
    public function setFirmwareVersion(?string $v): static { $this->firmwareVersion = $v; return $this; }
    public function getPlatform(): ?Platform { return $this->platform; }
    public function setPlatform(?Platform $v): static { $this->platform = $v; return $this; }
}
