<?php

namespace LinkORB\Bundle\WikiBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Proxies\__CG__\LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Yaml\Yaml;
use League\CommonMark\CommonMarkConverter;

class WikiService
{
    private $wikiRepository;
    private $wikiPageRepository;
    private $em;
    private $wikiEventService;
    private $authorizationChecker;

    public function __construct(
        WikiRepository $wikiRepository,
        WikiPageRepository $wikiPageRepository,
        EntityManagerInterface $em,
        WikiEventService $wikiEventService,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->wikiRepository = $wikiRepository;
        $this->wikiPageRepository = $wikiPageRepository;
        $this->em = $em;
        $this->wikiEventService = $wikiEventService;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function getAllWikis()
    {
        return $this->wikiRepository->findAll();
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

    public function searchWiki(string $text, array $wikiIds)
    {
        return $this->wikiPageRepository->searWikiPages($text, $wikiIds);
    }

    public function getToc(Wiki $wiki, array &$toc, $parentId = 0, $level = 0)
    {
        $pages = $this->wikiPageRepository->findByWikiIdAndParentId($wiki->getId(), $parentId);
        foreach ($pages as $page) {
            $toc[] = [
                'page' => $page,
                'level' => $level,
            ];
            $this->getToc($wiki, $toc, $page->getId(), $level+1);
            // echo $page->getName();
        }
    }

    public function renderSingleMarkdown(Wiki $wiki): ?string
    {
        $toc = [];
        $this->getToc($wiki, $toc);

        $markdown = '';
        foreach ($toc as $tocEntry) {
            $page = $tocEntry['page'];
            $pageContent = $page->getContent() ;

            $markdown .= '<!-- page:' . $page->getName() . ' -->' . PHP_EOL;
            $markdown .= trim($pageContent) . PHP_EOL . PHP_EOL;
        }

        $markdown = $this->processTwig($wiki, $markdown);
        return $markdown;
    }

    public function processTwig(Wiki $wiki, string $content, array $extra = []): ?string
    {

        $templates = [];
        $loader = new \Twig\Loader\ArrayLoader($templates);

        $twig = new \Twig\Environment($loader);
        $template = $twig->createTemplate($content);

        $config = Yaml::parse($wiki->getConfig() ?? '');

        $variables = [
            'data' => $config['data'] ?? [],
        ];
        foreach ($extra as $k=>$v) {
            $variables[$k] = $v;
        }
        // print_r($variables);
        $content = $template->render($variables);
        return $content;
    }


    public function getWikiPermission(Wiki $wiki)
    {
        $wikiRoles = ['readRole' => false, 'writeRole' => false];
        $flag = false;

        if ($this->authorizationChecker->isGranted('ROLE_SUPERUSER')) {
            $wikiRoles['readRole'] = true;
            $wikiRoles['writeRole'] = true;
            $flag = true;
        } else {
            if (!empty($wiki->getReadRole())) {
                $readArray = explode(',', $wiki->getReadRole());
                $readArray = array_map('trim', $readArray);

                foreach ($readArray as $read) {
                    if ($this->authorizationChecker->isGranted($read)) {
                        $wikiRoles['readRole'] = true;
                        $flag = true;
                    }
                }
            }

            if (!empty($wiki->getWriteRole())) {
                $writeArray = explode(',', $wiki->getWriteRole());
                $writeArray = array_map('trim', $writeArray);

                foreach ($writeArray as $write) {
                    if ($this->authorizationChecker->isGranted($write)) {
                        $flag = true;
                        $wikiRoles['writeRole'] = true;
                    }
                }
            }
        }

        return $flag ? $wikiRoles : false;
    }

    public function markdownToHtml(Wiki $wiki, ?string $markdown): ?string
    {
        // preprocess mediawiki style links (convert mediawiki style links into markdown links)
        preg_match_all('/\[\[(.+?)\]\]/u', $markdown, $matches);
        foreach ($matches[1] as $match) {
            $inner = (string) $match;
            $part = explode('|', $inner);
            $label = $part[0];
            $link = null;
            if (count($part) > 1) {
                $label = $part[1];
                $link = $part[0];
            }
            if (!$link) {
                $link = $label;
            }
            $link = trim(strtolower($link));
            $link = str_replace(' ', '-', $link);
            $markdown = str_replace('[['.$inner.']]', '['.$label.']('.$link.')', $content);
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        
        
        $html = $converter->convert($markdown);
        return $html;
    }
}
