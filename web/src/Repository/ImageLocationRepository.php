<?php

namespace App\Repository;

use App\Entity\ImageLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ImageLocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageLocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageLocation[]    findAll()
 * @method ImageLocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageLocationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ImageLocation::class);
    }
}
