<?php

namespace LinkORB\Bundle\WikiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method WikiEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method WikiEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method WikiEvent[]    findAll()
 * @method WikiEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WikiEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WikiEvent::class);
    }

    public function findByWikiId($wikiId)
    {
        return $this->findBy(['wiki_id' => $wikiId]);
    }
}
