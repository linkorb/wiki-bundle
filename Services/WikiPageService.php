<?php

namespace LinkORB\Bundle\WikiBundle\Services;

use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;

class WikiPageService
{
    private $wikiPageRepository;
    private $chain = [];

    public function __construct(WikiPageRepository $wikiPageRepository)
    {
        $this->wikiPageRepository = $wikiPageRepository;
    }

    protected function makeSpaces(int $level): string
    {
        $spacer = '';
        for ($i = 0; $i < $level; ++$i) {
            $spacer .= '...';
        }

        return $spacer;
    }

    public function pageRecursiveArray(int $wikiId, int $parentId = 0, int $skipId = 0, int $level = 0)
    {
        static $array = [];

        $spacer = $this->makeSpaces($level);

        foreach ($this->getByWikiIdAndParentId($wikiId, $parentId) as $wikiPage) {
            if ($wikiPage->getId() == $skipId) {
                continue;
            }

            $array[$wikiPage->getId()] = $spacer.$wikiPage->getName();

            $this->pageRecursiveArray($wikiId, $wikiPage->getId(), $skipId, $level + 1);
        }

        return $array;
    }

    public function getOneByWikiIdAndPageName(int $wikiId, string $pageName)
    {
        return $this->wikiPageRepository->findOneByWikiIdAndName($wikiId, $pageName);
    }

    public function getByWikiIdAndParentId(int $wikiId, int $parentId = 0)
    {
        return $this->wikiPageRepository->findByWikiIdAndParentId($wikiId, $parentId);
    }

    public function recursiveChild(WikiPage $wikiPage)
    {
        $childPages = $this->wikiPageRepository->findByParentId($wikiPage->getId());
        foreach ($childPages as $childPage) {
            $childPage->setChildPages($this->recursiveChild($childPage));
        }

        return $childPages;
    }

    public function getChain(int $wikiId, WikiPage $wikiPage)
    {
        array_unshift($this->chain, $wikiPage);

        if ($wikiPage->getParentId()) {
            $parentWikiPage = $this->wikiPageRepository->findOneByWikiIdAndId($wikiId, $wikiPage->getParentId());
            $this->getChain($wikiId, $parentWikiPage);
        }

        return $this->chain;
    }

    public function breadcrumbs(int $wikiId, int $wikiPageId): array
    {
        $this->chain = [];

        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndId($wikiId, $wikiPageId);

        $this->getChain($wikiId, $wikiPage);

        return $this->chain;
    }
}
