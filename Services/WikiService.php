<?php

namespace LinkORB\Bundle\WikiBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Proxies\__CG__\LinkORB\Bundle\WikiBundle\Entity\WikiPage;

class WikiService
{
    private $wikiRepository;
    private $wikiPageRepository;
    private $em;
    private $wikiEventService;

    public function __construct(
        WikiRepository $wikiRepository,
        WikiPageRepository $wikiPageRepository,
        EntityManagerInterface $em,
        WikiEventService $wikiEventService
    ) {
        $this->wikiRepository = $wikiRepository;
        $this->wikiPageRepository = $wikiPageRepository;
        $this->em = $em;
        $this->wikiEventService = $wikiEventService;
    }

    public function getWikiByName(string $wikiName)
    {
        return $this->wikiRepository->findOneByName($wikiName);
    }

    public function export(Wiki $wiki): array
    {
        $array = [
            'name' => $wiki->getName(),
            'description' => $wiki->getDescription(),
            'readRole' => $wiki->getReadRole(),
            'writeRole' => $wiki->getWriteRole(),
            'config' => $wiki->getConfig(),
            'pages' => $this->wikiPages($wiki),
        ];

        return $array;
    }

    public function wikiPages(Wiki $wiki): array
    {
        $array = [];
        foreach ($wiki->getWikiPages() as $wikiPage) {
            $parentWikiPage = $this->wikiPageRepository->find($wikiPage->getParentId());

            $array[$wikiPage->getName()] = [
                'name' => $wikiPage->getName(),
                'content' => $wikiPage->getContent(),
                'data' => $wikiPage->getData(),
                'parent' => $parentWikiPage ? $parentWikiPage->getName() : null,
            ];
        }

        usort($array, function ($a, $b) {
            return $a['parent'] ? 1 : 0;
        });

        return $array;
    }

    public function import(Wiki $wiki, array $wikiArray)
    {
        $wiki
            ->setDescription($wikiArray['description'])
            ->setReadRole($wikiArray['readRole'])
            ->setWriteRole($wikiArray['writeRole'])
            ->setConfig($wikiArray['config']);

        $this->em->persist($wiki);
        $this->em->flush();

        $this->wikiEventService->createEvent(
            'wiki.updated',
            $wiki->getId(),
            json_encode([
                'createdAt' => time(),
                'createdBy' => '',
                'name' => $wiki->getName(),
                'description' => $wiki->getDescription(),
            ])
        );

        foreach ($wikiArray['pages'] as $wikiPageArray) {
            $type = 'page.updated';

            if (!$wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $wikiPageArray['name'])) {
                $wikiPage = new WikiPage();
                $wikiPage
                    ->setName($wikiPageArray['name'])
                    ->setWiki($wiki);

                $type = 'page.created';
            }

            $parentId = 0;
            if (!empty($wikiPageArray['parent'])) {
                if ($parentWikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $wikiPageArray['parent'])) {
                    $parentId = $parentWikiPage->getId();
                }
            }

            $wikiPage
                ->setContent($wikiPageArray['content'])
                ->setData($wikiPageArray['data'])
                ->setParentId($parentId);

            $this->em->persist($wikiPage);
            $this->em->flush();

            $this->wikiEventService->createEvent(
                $type,
                $wikiPage->getWiki()->getId(),
                json_encode([
                    'updatedAt' => time(),
                    'updatedBy' => '',
                    'name' => $wikiPage->getName(),
                ]),
                $wikiPage->getId()
            );
        }
    }
}
