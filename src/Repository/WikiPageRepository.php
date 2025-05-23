<?php

namespace LinkORB\Bundle\WikiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;

/**
 * @method WikiPage|null find($id, $lockMode = null, $lockVersion = null)
 * @method WikiPage|null findOneBy(array $criteria, array $orderBy = null)
 * @method WikiPage[]    findAll()
 * @method WikiPage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WikiPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WikiPage::class);
    }

    public function findByWikiId($wikiId): array
    {
        return $this->findBy(['wiki' => $wikiId]);
    }

    public function findOneByWikiIdAndName($wikiId, $name): ?WikiPage
    {
        return $this->findOneBy(['wiki' => $wikiId, 'name' => $name]);
    }

    public function findByWikiIdAndParentId(int $wikiId, int $parentId): array
    {
        return $this->findBy(['wiki' => $wikiId, 'parent_id' => $parentId]);
    }

    public function findByParentId(int $parentId): array
    {
        return $this->findBy(['parent_id' => $parentId]);
    }

    public function findOneByWikiIdAndId(int $wikiId, int $id): ?WikiPage
    {
        return $this->findOneBy(['wiki' => $wikiId, 'id' => $id]);
    }

    public function searWikiPages(string $text, array $wikiIds)
    {
        return $this->createQueryBuilder('wp')
            ->select('wp,
                CASE
                    WHEN wp.name LIKE :val THEN 3
                    WHEN wp.content LIKE :val THEN 2
                    WHEN wp.data LIKE :val THEN 1
                ELSE 0
                END as points
            ')
            ->setParameter('val', '%'.$text.'%')
            ->andHaving('points > 0')
            ->orderBy('points', 'DESC')
            ->where('wp.wiki IN(:wikIds)')
            ->setParameter('wikIds', array_values($wikiIds))
            ->getQuery()
            ->getResult();
    }
}
