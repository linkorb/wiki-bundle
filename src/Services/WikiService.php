<?php

namespace LinkORB\Bundle\WikiBundle\Services;

use CzProject\GitPhp\Git;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Table\TableExtension;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Yaml\Yaml;

class WikiService
{
    private $wikiRepository;
    private $wikiPageRepository;
    private $em;
    private $wikiEventService;
    private $authorizationChecker;
    private $gitDirPath;
    private $git;

    public function __construct(
        WikiRepository $wikiRepository,
        WikiPageRepository $wikiPageRepository,
        EntityManagerInterface $em,
        WikiEventService $wikiEventService,
        AuthorizationCheckerInterface $authorizationChecker,
        private readonly ParameterBagInterface $params,
    ) {
        $this->wikiRepository = $wikiRepository;
        $this->wikiPageRepository = $wikiPageRepository;
        $this->em = $em;
        $this->wikiEventService = $wikiEventService;
        $this->authorizationChecker = $authorizationChecker;
        $this->gitDirPath = $this->params->get('kernel.project_dir').'/var/wiki';
        $this->git = new Git();
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

            $wikiPageBeforeTitle = $wikiPage->getName();
            $wikiPageBeforeContent = $wikiPage->getContent();

            $wikiPage
                ->setContent($wikiPageArray['content'])
                ->setData($wikiPageArray['data'])
                ->setParentId($parentId);

            $this->em->persist($wikiPage);
            $this->em->flush();

            $eventData = [
                'updatedAt' => time(),
                'updatedBy' => '',
                'name' => $wikiPage->getName(),
            ];
            if (0 !== strcmp($wikiPageBeforeTitle, $wikiPage->getName())) {
                $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                    'title',
                    $wikiPageBeforeTitle,
                    $wikiPage->getName()
                );
            }
            if (0 !== strcmp($wikiPageBeforeContent, $wikiPage->getContent())) {
                $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                    'content',
                    $wikiPageBeforeContent,
                    $wikiPage->getContent()
                );
            }

            $this->wikiEventService->createEvent(
                $type,
                $wikiPage->getWiki()->getId(),
                json_encode($eventData),
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
            $this->getToc($wiki, $toc, $page->getId(), $level + 1);
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
            $pageContent = $page->getContent();

            $markdown .= '<!-- page:'.$page->getName().' -->'.PHP_EOL;
            $markdown .= trim($pageContent).PHP_EOL.PHP_EOL;
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

        $parts = [
            'data' => $config['data'] ?? [],
        ];
        foreach ($extra as $k => $v) {
            $parts[$k] = $v;
        }
        // print_r($parts);
        $content = $template->render($parts);

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
            $markdown = str_replace('[['.$inner.']]', '['.$label.']('.$link.')', $markdown);
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => ['class' => 'table-responsive table table-bordered', 'style' => 'border: none;'],
                ],
            ],
        ]);

        $environment = $converter->getEnvironment();
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AttributesExtension());

        $html = $converter->convert($markdown ?? '');

        return $html;
    }

    public function publishWiki(Wiki $wiki, string $username, string $userEmail)
    {
        foreach ($wiki->getWikiPages() as $wikiPage) {
            $this->publishWikiPage($wiki, $wikiPage, $username, $userEmail);
        }
    }

    public function publishWikiPage(Wiki $wiki, WikiPage $wikiPage, string $username, string $userEmail)
    {
        $wikiPath = $this->gitDirPath.'/'.$wiki->getName();
        if (!is_dir($wikiPath)) {
            mkdir($wikiPath, 0777, true);
        }
        if (!is_dir($wikiPath.'/.git')) {
            $repo = $this->git->init($wikiPath, [
                '--initial-branch=main',
            ]);
            $repo->execute('config', 'user.name', $username);
            $repo->execute('config', 'user.email', $userEmail);
        } else {
            $repo = $this->git->open($wikiPath);
            $repo->execute('config', 'committer.name', $username);
            $repo->execute('config', 'committer.email', $userEmail);
        }

        $contentFile = $wikiPath.'/'.$wikiPage->getName().'.md';
        $dataFile = $wikiPath.'/'.$wikiPage->getName().'.yaml';

        if (!file_exists($contentFile)) {
            file_put_contents($contentFile, '');
        }
        if (!file_exists($dataFile)) {
            file_put_contents($dataFile, '');
        }

        if (0 !== strcmp($wikiPage->getContent(), file_get_contents($contentFile))) {
            file_put_contents($contentFile, $wikiPage->getContent());
        }
        if (0 !== strcmp($wikiPage->getData(), file_get_contents($dataFile))) {
            file_put_contents($dataFile, $wikiPage->getData());
        }

        if ($repo->hasChanges()) {
            $repo->addAllChanges();
            // exit('yo');
            $repo->commit('docs: '.$wikiPage->getName().' updated', ["--author={$username} <{$userEmail}>"]);

            $pushConfig = [];
            foreach ($wiki->getConfigArray()['push'] ?? [] as $wikiPushConfig) {
                if ('git' == $wikiPushConfig['type']) {
                    $pushConfig = $wikiPushConfig;
                    break;
                }
            }

            if ($pushConfig) {
                $remote = $repo->execute('remote', 'show');
                if (!in_array('origin', $remote)) {
                    $repo->addRemote('origin', $pushConfig['url']);
                }

                if (!empty($pushConfig['secret'])) {
                    $secret = $this->params->get($pushConfig['secret']);
                    if (!$secret) {
                        throw new \RuntimeException('Unable to resolve secret: '.$pushConfig['secret']);
                    }
                    $parseUrl = parse_url($pushConfig['url']);
                    $parseUrl['pass'] = $secret;
                    $pushConfig['url'] = $this->unParseUrl($parseUrl);
                }

                $repo->push(null, [$pushConfig['url']]);
            }
        }
    }

    public function pull(Wiki $wiki)
    {
        $wikiPath = $this->gitDirPath.'/'.$wiki->getName();

        if (!is_dir($wikiPath.'/.git')) {
            $repo = $this->git->init($wikiPath, [
                '--initial-branch=main',
            ]);
        } else {
            $repo = $this->git->open($wikiPath);
        }

        $repo = $this->git->open($wikiPath);

        $pullConfig = [];
        foreach ($wiki->getConfigArray()['pull'] ?? [] as $wikiPullConfig) {
            if ('git' == $wikiPullConfig['type']) {
                $pullConfig = $wikiPullConfig;
                break;
            }
        }

        if ($pullConfig) {
            $remote = $repo->execute('remote', 'show');
            if (!in_array('origin', $remote)) {
                $repo->addRemote('origin', $pullConfig['url']);
            }

            if (!empty($pullConfig['secret'])) {
                $secret = $this->params->get($pullConfig['secret']);
                if (!$secret) {
                    throw new \RuntimeException('Unable to resolve secret: '.$pullConfig['secret']);
                }
                if ($secret) {
                    $parseUrl = parse_url($pullConfig['url']);
                    $parseUrl['pass'] = $secret;
                    $pullConfig['url'] = $this->unParseUrl($parseUrl);
                }
            }
            $repo->pull(null, [$pullConfig['url']]);

            $this->importDirectory($wiki, $wikiPath);

            $wiki->setLastPullAt(time());
            $this->em->persist($wiki);
            $this->em->flush();
        }
    }

    public function importDirectory(Wiki $wiki, string $path)
    {
        if (!is_dir($path)) {
            throw new \RuntimeException('Directory not found:'.$path, 1);
        }

        foreach (new \DirectoryIterator($path) as $fileInfo) {
            $type = 'page.updated';

            if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                continue;
            }

            if ('md' == $fileInfo->getExtension()) {
                $wikiPageName = rtrim($fileInfo->getFilename(), '.md');
                if (!$wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $wikiPageName)) {
                    $wikiPage = new WikiPage();
                    $wikiPage
                        ->setName($wikiPageName)
                        ->setWiki($wiki)
                        ->setparentId(0)
                    ;

                    $type = 'page.created';
                }

                $wikiPageBeforeTitle = $wikiPage->getName();
                $wikiPageBeforeContent = $wikiPage->getContent();

                $wikiPage->setContent(file_get_contents($fileInfo->getPathname()));

                $yamlFile = $fileInfo->getPath()."/{$wikiPageName}.yaml";
                if (file_exists($yamlFile)) {
                    $wikiPage->setData(file_get_contents($yamlFile));
                }

                $this->em->persist($wikiPage);
                $this->em->flush();

                $eventData = [
                    'updatedAt' => time(),
                    'updatedBy' => '',
                    'name' => $wikiPage->getName(),
                ];
                if (0 !== strcmp($wikiPageBeforeTitle, $wikiPage->getName())) {
                    $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                        'title',
                        $wikiPageBeforeTitle,
                        $wikiPage->getName()
                    );
                }
                if (0 !== strcmp($wikiPageBeforeContent, $wikiPage->getContent())) {
                    $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                        'content',
                        $wikiPageBeforeContent,
                        $wikiPage->getContent()
                    );
                }

                $this->wikiEventService->createEvent(
                    $type,
                    $wikiPage->getWiki()->getId(),
                    json_encode($eventData),
                    $wikiPage->getId()
                );
            }
        }
    }

    public function autoPull(Wiki $wiki)
    {
        $dateTime = (new \DateTime(' -5 minutes'))->getTimestamp();

        if ((int) $wiki->getLastPullAt() <= $dateTime) {
            $this->pull($wiki);
        }
    }

    private function unParseUrl(array $parseUrl): ?string
    {
        $scheme = isset($parseUrl['scheme']) ? $parseUrl['scheme'].'://' : '';
        $host = isset($parseUrl['host']) ? $parseUrl['host'] : '';
        $port = isset($parseUrl['port']) ? ':'.$parseUrl['port'] : '';
        $user = isset($parseUrl['user']) ? $parseUrl['user'] : '';
        $pass = isset($parseUrl['pass']) ? (isset($parseUrl['user']) ? ':' : '').$parseUrl['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parseUrl['path']) ? $parseUrl['path'] : '';
        $query = isset($parseUrl['query']) ? '?'.$parseUrl['query'] : '';
        $fragment = isset($parseUrl['fragment']) ? '#'.$parseUrl['fragment'] : '';

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
    }
}
