<?php

namespace LinkORB\Bundle\WikiBundle\Repository;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Wiki|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wiki|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wiki[]    findAll()
 * @method Wiki[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WikiRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Wiki::class);
    }

    public function findOneByName($name)
    {
        return $this->findOneBy(['name' => $name]);
    }
}
