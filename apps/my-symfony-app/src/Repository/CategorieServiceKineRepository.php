<?php

namespace App\Repository;

use App\Entity\CategorieServiceKine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorieServiceKine>
 *
 * @method CategorieServiceKine|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategorieServiceKine|null findOneBy(array $criteria, array $orderBy = null)
 * @method CategorieServiceKine[]    findAll()
 * @method CategorieServiceKine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieServiceKineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieServiceKine::class);
    }

//    /**
//     * @return CategorieServiceKine[] Returns an array of CategorieServiceKine objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?CategorieServiceKine
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
