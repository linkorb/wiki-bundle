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
use LinkORB\Component\Llm\LlmManager;
use LinkORB\Component\Llm\Prompt\PromptManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityPolicy;

class WikiService
{
    private $git;

    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly WikiPageRepository $wikiPageRepository,
        private readonly EntityManagerInterface $em,
        private readonly WikiEventService $wikiEventService,
        private readonly ParameterBagInterface $params,
        private readonly LlmManager $llmManager,
        private readonly PromptManagerInterface $promptManager,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'wiki_bundle_data_dir')]
        private readonly string $gitDirPath
    ) {
        $this->git = new Git();
    }

    /**
     * @return Wiki[]
     */
    public function getAllWikis(): array
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

        usort($array, fn($a, $b) => $a['parent'] ? 1 : 0);

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
            if (0 !== strcmp((string) $wikiPageBeforeTitle, (string) $wikiPage->getName())) {
                $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                    'title',
                    $wikiPageBeforeTitle,
                    $wikiPage->getName()
                );
            }
            if (0 !== strcmp((string) $wikiPageBeforeContent, (string) $wikiPage->getContent())) {
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

    /**
     * @param string $text
     * @param int[] $wikiIds
     * @return array<int,array{0: WikiPage, points: int}>
     */
    public function searchWiki(string $text, array $wikiIds): array
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
            $markdown .= trim((string) $pageContent).PHP_EOL.PHP_EOL;
        }

        $markdown = $this->processTwig($wiki, $markdown);

        return $markdown;
    }

    public function processTwig(Wiki $wiki, string $content, array $extra = []): ?string
    {
        $templates = [];
        $loader = new \Twig\Loader\ArrayLoader($templates);

        $twig = new \Twig\Environment($loader);

        // Define minimal safe tags, filters, and functions for sandbox
        // Explicitly excludes: include, embed, use, import, extends, macro, sandbox
        $allowedTags = ['if', 'for', 'set', 'spaceless', 'autoescape', 'verbatim', 'apply'];
        $allowedFilters = [
            'escape', 'e', 'raw',
            'upper', 'lower', 'capitalize', 'title', 'trim',
            'nl2br', 'join', 'split', 'replace',
            'length', 'first', 'last', 'slice', 'reverse',
            'default', 'abs', 'round', 'number_format',
            'date', 'date_modify', 'format',
            'json_encode', 'url_encode',
            'keys', 'merge', 'sort', 'batch', 'column',
        ];
        $allowedFunctions = [
            'range', 'cycle', 'random', 'min', 'max', 'date',
        ];
        // No object methods or properties allowed for security
        $allowedMethods = [];
        $allowedProperties = [];

        $policy = new SecurityPolicy(
            $allowedTags,
            $allowedFilters,
            $allowedMethods,
            $allowedProperties,
            $allowedFunctions
        );

        $sandbox = new SandboxExtension($policy, true);
        $twig->addExtension($sandbox);

        $template = $twig->createTemplate($content);

        $config = Yaml::parse($wiki->getConfig() ?? '');

        $parts = [
            'data' => $config['data'] ?? [],
        ];
        foreach ($extra as $k => $v) {
            $parts[$k] = $v;
        }

        $content = $template->render($parts);

        return $content;
    }

    public function markdownToHtml(Wiki $wiki, ?string $markdown): ?string
    {
        // preprocess mediawiki style links (convert mediawiki style links into markdown links)
        preg_match_all('/\[\[(.+?)\]\]/u', (string) $markdown, $matches);
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

        if (0 !== strcmp((string) $wikiPage->getContent(), file_get_contents($contentFile))) {
            file_put_contents($contentFile, $wikiPage->getContent());
        }
        if (0 !== strcmp((string) $wikiPage->getData(), file_get_contents($dataFile))) {
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
                    $parseUrl = parse_url((string) $pushConfig['url']);
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
                    $parseUrl = parse_url((string) $pullConfig['url']);
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
                if (0 !== strcmp((string) $wikiPageBeforeTitle, (string) $wikiPage->getName())) {
                    $eventData['changes'][] = $this->wikiEventService->fieldDataChangeArray(
                        'title',
                        $wikiPageBeforeTitle,
                        $wikiPage->getName()
                    );
                }
                if (0 !== strcmp((string) $wikiPageBeforeContent, (string) $wikiPage->getContent())) {
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

    public function autoPull(Wiki $wiki): void
    {
        $dateTime = (new \DateTime(' -5 minutes'))->getTimestamp();

        if ((int) $wiki->getLastPullAt() <= $dateTime) {
            $this->pull($wiki);
        }
    }

    /**
     * Build the data array for LLM prompt, including styleguide if available.
     *
     * @return array<string, mixed>
     */
    public function buildGeneratePromptData(WikiPage $wikiPage, ?string $context = null): array
    {
        $contextToUse = $context ?? $wikiPage->getContext();

        if (empty($contextToUse)) {
            throw new \RuntimeException('No context provided for AI content generation');
        }

        $wiki = $wikiPage->getWiki();
        $wikiId = $wiki?->getId();

        $data = [
            'pageName' => $wikiPage->getName(),
            'context' => $contextToUse,
            'wikiName' => $wiki?->getName(),
            'wikiDescription' => $wiki?->getDescription(),
            'styleguide' => '',
        ];

        // Look for a page named "context" in the same wiki to use as styleguide
        if ($wikiId !== null) {
            $contextPage = $this->wikiPageRepository->findOneByWikiIdAndName($wikiId, 'context');
            if ($contextPage && $contextPage->getContent()) {
                $data['styleguide'] = $contextPage->getContent();
            }
        }

        return $data;
    }

    /**
     * Get a preview of the LLM request that would be sent.
     *
     * @return array<string, mixed>
     */
    public function previewGenerateRequest(WikiPage $wikiPage, ?string $context = null): array
    {
        $data = $this->buildGeneratePromptData($wikiPage, $context);
        $prompt = $this->promptManager->getPrompt('wiki_page_generate');

        // Build the messages array as it would be sent to the LLM
        $messages = [];
        foreach ($prompt->getMessages() as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';

            // Replace template variables
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $content = str_replace('{{' . $key . '}}', $value, $content);
                }
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return [
            'model' => 'default',
            'promptName' => 'wiki_page_generate',
            'data' => $data,
            'messages' => $messages,
            'hasSchema' => $prompt->hasSchema(),
        ];
    }

    /**
     * Generate page content using AI based on the context field.
     */
    public function generatePageContent(WikiPage $wikiPage, ?string $context = null): string
    {
        $data = $this->buildGeneratePromptData($wikiPage, $context);

        try {
            $llm = $this->llmManager->getLlm('default');
            $prompt = $this->promptManager->getPrompt('wiki_page_generate');

            $completion = $llm->complete($prompt, $data);
            $result = $completion->getContent();

            if ($prompt->hasSchema()) {
                $resultData = json_decode((string) $result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('JSON parsing failed during wiki page generation', [
                        'pageId' => $wikiPage->getId(),
                        'pageName' => $wikiPage->getName(),
                        'jsonError' => json_last_error(),
                        'jsonErrorMessage' => json_last_error_msg(),
                    ]);
                    throw new \RuntimeException('JSON parsing error: ' . json_last_error_msg());
                }

                // Store the full generated result
                $wikiPage->setGenerated(json_encode($resultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $wikiPage->setGeneratedAt(time());

                // Return the content field from the result
                /** @var array<string, mixed> $resultData */
                $content = $resultData['content'] ?? '';
            } else {
                $content = (string) $result;
            }

            return $content;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate wiki page content', [
                'pageId' => $wikiPage->getId(),
                'pageName' => $wikiPage->getName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function unParseUrl(array $parseUrl): ?string
    {
        $scheme = isset($parseUrl['scheme']) ? $parseUrl['scheme'].'://' : '';
        $host = $parseUrl['host'] ?? '';
        $port = isset($parseUrl['port']) ? ':'.$parseUrl['port'] : '';
        $user = $parseUrl['user'] ?? '';
        $pass = isset($parseUrl['pass']) ? (isset($parseUrl['user']) ? ':' : '').$parseUrl['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parseUrl['path'] ?? '';
        $query = isset($parseUrl['query']) ? '?'.$parseUrl['query'] : '';
        $fragment = isset($parseUrl['fragment']) ? '#'.$parseUrl['fragment'] : '';

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
    }
}
