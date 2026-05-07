<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EvidenceItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class EvidenceItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvidenceItem::class);
    }
}
