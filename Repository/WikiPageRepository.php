<?php

namespace LinkORB\Bundle\WikiBundle\Repository;

use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method WikiPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method WikiPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method WikiPage[]    findAll()
 * @method WikiPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WikiPageRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, WikiPage::class);
    }

//    /**
//     * @return WikiPage[] Returns an array of WikiPage objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('w.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?WikiPage
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findByWikiId($wikiId)
    {
        return $this->findBy(['wiki' => $wikiId]);
    }

    public function findOneByWikiIdAndName($wikiId, $name)
    {
        return $this->findOneBy(['wiki' => $wikiId, 'name' => $name]);
    }
}
