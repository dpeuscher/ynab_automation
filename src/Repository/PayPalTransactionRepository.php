<?php

namespace App\Repository;

use App\Entity\PayPalTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PayPalTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method PayPalTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method PayPalTransaction[]    findAll()
 * @method PayPalTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PayPalTransactionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PayPalTransaction::class);
    }

    /**
     * @param string $checksum
     * @return PayPalTransaction[] Returns an array of PayPalTransaction objects
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
