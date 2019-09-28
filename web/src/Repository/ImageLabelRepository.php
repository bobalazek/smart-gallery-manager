<?php

namespace App\Repository;

use App\Entity\ImageLabel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ImageLabel|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageLabel|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageLabel[]    findAll()
 * @method ImageLabel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageLabelRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ImageLabel::class);
    }
}
