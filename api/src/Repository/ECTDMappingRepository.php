<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ECTDMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ECTDMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ECTDMapping::class);
    }
}
