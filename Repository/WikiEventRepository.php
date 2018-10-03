<?php

namespace LinkORB\Bundle\WikiBundle\Repository;

use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method WikiEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method WikiEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method WikiEvent[]    findAll()
 * @method WikiEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WikiEventRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, WikiEvent::class);
    }

    public function findByWikiId($wikiId)
    {
        return $this->findBy(['wiki_id' => $wikiId]);
    }
}
