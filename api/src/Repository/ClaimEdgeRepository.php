<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ClaimEdge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ClaimEdgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClaimEdge::class);
    }
}
