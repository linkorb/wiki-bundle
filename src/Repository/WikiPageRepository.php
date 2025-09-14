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

    /**
     * @param int $wikiId
     * @return WikiPage[]
     */
    public function findByWikiId(int $wikiId): array
    {
        return $this->findBy(['wiki' => $wikiId]);
    }

    public function findOneByWikiIdAndName(int $wikiId, string $name): ?WikiPage
    {
        return $this->findOneBy(['wiki' => $wikiId, 'name' => $name]);
    }

    /**
     * @param int $wikiId
     * @param int $parentId
     * @return WikiPage[]
     */
    public function findByWikiIdAndParentId(int $wikiId, int $parentId): array
    {
        return $this->findBy(['wiki' => $wikiId, 'parent_id' => $parentId]);
    }

    /**
     * @param int $parentId
     * @return WikiPage[]
     */
    public function findByParentId(int $parentId): array
    {
        return $this->findBy(['parent_id' => $parentId]);
    }

    public function findOneByWikiIdAndId(int $wikiId, int $id): ?WikiPage
    {
        return $this->findOneBy(['wiki' => $wikiId, 'id' => $id]);
    }

    /**
     * @param string $text
     * @param int[] $wikiIds
     * @return array<int,array{0: WikiPage, points: int}>
     */
    public function searWikiPages(string $text, array $wikiIds): array
    {
        /** @phpstan-ignore-next-line  */
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
