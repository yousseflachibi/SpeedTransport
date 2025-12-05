<?php

namespace App\Repository;

use App\Entity\ServiceKine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceKine>
 */
class ServiceKineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceKine::class);
    }

    // add custom queries if needed
}
