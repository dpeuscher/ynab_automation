<?php

namespace App\Repository;

use App\Entity\HbciTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method HbciTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method HbciTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method HbciTransaction[]    findAll()
 * @method HbciTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HbciTransactionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, HbciTransaction::class);
    }

    /**
     * @param string $checksum
     * @return HbciTransaction[] Returns an array of HbciTransaction objects
     */
    public function findByChecksum(string $checksum): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.checksum = :checksum')
            ->setParameter('checksum', $checksum)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}
