<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use LinkORB\Bundle\NebulaBundle\Attribute\Breadcrumb;
use LinkORB\Bundle\NebulaBundle\Attribute\NebulaNav;
use LinkORB\Bundle\NebulaBundle\Contracts\Breadcrumb as BreadcrumbType;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Form\WikiSearchType;
use LinkORB\Bundle\WikiBundle\Form\WikiType;
use LinkORB\Bundle\WikiBundle\Services\WikiEventService;
use LinkORB\Bundle\WikiBundle\Services\WikiPageService;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Yaml\Yaml;

#[Route('/wiki')]
class WikiController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/wikis', name: 'wiki_index', methods: ['GET'])]
    #[Breadcrumb(
        label: 'Wikis',
        parentRoute: 'wiki_lander',
        icon: 'icon-lux-article',
        title: 'Browse all wikis',
        type: BreadcrumbType::TYPE_ENTITY_INDEX,
    )]
    public function indexAction(WikiService $wikiService): Response
    {
        $wikis = $wikiService->getAllWikis();

        $wikiArray = [];
        foreach ($wikis as $wiki) {
            if ($this->isGranted('access', $wiki)) {
                $wikiArray[] = $wiki;
            }
        }

        usort($wikiArray, fn($a, $b) => strcmp((string) $a->getName(), (string) $b->getName()));

        return $this->render(
            '@LinkORBWiki/wiki/index.html.twig',
            ['wikis' => $wikiArray]
        );
    }

    #[Route('/add', name: 'wiki_add', methods: ['GET', 'POST'])]
    #[Breadcrumb(
        label: 'New Wiki',
        parentRoute: 'wiki_index',
        icon: 'icon-lux-add',
        title: 'Create new wiki',
        type: BreadcrumbType::TYPE_ACTION,
    )]
    public function addAction(Request $request, WikiEventService $wikiEventService): Response
    {
        $this->denyAccessUnlessGranted('create', 'wikis');
        $wiki = new Wiki();
        /** @var string|null $wiki_name */
        $wiki_name = $request->get('wikiname');
        if (!empty($wiki_name)) {
            $wiki->setName($wiki_name);
        }
        return $this->getEditForm($request, $wiki, $wikiEventService, true);
    }

    #[Route('/search', name: 'wiki_search', methods: ['GET', 'POST'])]
    #[Breadcrumb(
        label: 'Search',
        parentRoute: 'wiki_index',
        icon: 'icon-lux-search',
        title: 'Search wikis',
        type: BreadcrumbType::TYPE_ACTION,
    )]
    public function searchAction(Request $request, WikiService $wikiService): Response
    {
        $wikiArray = [];
        $wikiIds = [];
        $wikiPages = [];

        foreach ($wikiService->getAllWikis() as $wiki) {
            if ($this->isGranted('access', $wiki)) {
                $wikiArray[$wiki->getName()] = $wiki->getName();
                /** @var int $id */
                $id = $wiki->getId();
                $wikiIds[] = $id;
            }
        }

        asort($wikiArray);
        $form = $this->createForm(
            WikiSearchType::class,
            ['wikiName' => $request->get('wikiName')],
            ['method' => 'GET', 'csrf_protection' => false, 'wikiArray' => $wikiArray]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var array{search: string, wikiName: string} $formData*/
            $formData = $form->getData();

            if (!empty($formData['wikiName'])) {
                $wiki = $wikiService->getWikiByName($formData['wikiName']);
                if (!$wiki instanceof Wiki) {
                    throw $this->createNotFoundException();
                }
                $this->denyAccessUnlessGranted('access', $wiki);
                /** @var int $id */
                $id = $wiki->getId();
                $wikiIds = [$id];
            }

            $wikiPageResults = [];
            if (!empty($formData['search'])) {
                $wikiPageResults = $wikiService->searchWiki($formData['search'], $wikiIds);
            }

            foreach ($wikiPageResults as $wikiPageResult) {
                $tmpVar = $wikiPageResult[0];
                $tmpVar->setPoints((int)$wikiPageResult['points']);
                $wikiPages[] = $tmpVar;
            }
        }

        return $this->render('@LinkORBWiki/wiki/search.html.twig', [
            'form' => $form->createView(),
            'wikiPages' => $wikiPages,
        ]);
    }

    #[Route('/{wikiName}/publish', name: 'wiki_publish', methods: ['GET'])]
    public function publishAction(#[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, WikiService $wikiService): Response
    {
        $this->denyAccessUnlessGranted('modify', $wiki);
        /** @var UserInterface $user */
        $user = $this->getUser();

        $wikiService->publishWiki($wiki, $user->getUserIdentifier(), $user->getEmail());

        return $this->redirectToRoute('wiki_view', ['wikiName' => $wiki->getName()]);
    }

    #[Route('/{wikiName}/edit', name: 'wiki_edit', methods: ['GET', 'POST'])]
    #[Breadcrumb(
        label: 'Edit',
        parentRoute: 'wiki_view',
        icon: 'icon-lux-edit',
        title: 'Edit wiki settings',
        type: BreadcrumbType::TYPE_ACTION,
    )]
    public function editAction(
        Request $request,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService
    ): Response
    {
        $this->denyAccessUnlessGranted('modify', $wiki);

        return $this->getEditForm($request, $wiki, $wikiEventService);
    }

    #[Route('/{wikiName}/delete', name: 'wiki_delete', methods: ['POST'])]
    public function deleteAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService,
        Request $request
    ): Response
    {
        $this->denyAccessUnlessGranted('delete', $wiki);
        if (!$this->isCsrfTokenValid('delete-item', (string) $request->getPayload()->get('token'))) {
            throw new BadRequestHttpException('CSRF token invalid!');
        }

        if (count($wiki->getWikiPages())) {
            $this->addFlash('error', 'The wiki cannot be deleted because of having a wiki-page.');
        } else {
            $wikiEventService
                ->createEvent(
                    'wiki.deleted',
                    $wiki->getId(),
                    json_encode([
                        'deletedAt' => time(),
                        'deletedBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                        'name' => $wiki->getName(),
                    ])
                );

            $this->em->remove($wiki);
            $this->em->flush();
        }

        return $this->redirectToRoute('wiki_index');
    }

    protected function getEditForm(Request $request, Wiki $wiki, WikiEventService $wikiEventService, bool $add = false): Response
    {
        $form = $this->createForm(WikiType::class, $wiki);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($wiki);
            $this->em->flush();

            if ($add) {
                $wikiEventService->createEvent(
                    'wiki.created',
                    $wiki->getId(),
                    json_encode([
                        'createdAt' => time(),
                        'createdBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                        'name' => $wiki->getName(),
                        'description' => $wiki->getDescription(),
                    ])
                );
            } else {
                $wikiEventService->createEvent(
                    'wiki.updated',
                    $wiki->getId(),
                    json_encode([
                        'updatedAt' => time(),
                        'updatedBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                        'name' => $wiki->getName(),
                        'description' => $wiki->getDescription(),
                    ])
                );
            }

            return $this->redirectToRoute('wiki_view', ['wikiName' => $wiki->getName()]);
        }

        return $this->render('@LinkORBWiki/wiki/edit.html.twig', [
            'wiki' => $wiki,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{wikiName}', name: 'wiki_view', methods: ['GET'])]
    #[Breadcrumb(
        label: '{wikiName}',
        parentRoute: 'wiki_index',
        icon: 'icon-lux-article',
        title: 'View wiki',
        type: BreadcrumbType::TYPE_ENTITY,
    )]
    #[NebulaNav(template: '@LinkORBWiki/nav/wiki-context.html.twig')]
    public function viewAction(
        WikiPageService $wikiPageService,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $data = [];

        /** @var int $wiki_id */
        $wiki_id = $wiki->getId();

        $indexPage = $wikiPageService->getOneByWikiIdAndPageName($wiki_id, 'index');
        if ($indexPage instanceof WikiPage) {
            return $this->redirectToRoute('wiki_page_view', [
                'wikiName' => $wiki->getName(),
                'pageName' => $indexPage->getName()
            ]);
        }

        /** @var WikiPage[] $wikiPages */
        $wikiPages = $wikiPageService->getByWikiIdAndParentId($wiki_id);

        foreach ($wikiPages as $wikiPage) {
            $wikiPage->setChildPages($wikiPageService->recursiveChild($wikiPage));
        }

        $data['wikiPages'] = $wikiPages;
        $data['wiki'] = $wiki;

        $wikiService->autoPull($wiki);

        return $this->render('@LinkORBWiki/wiki_page/index.html.twig', $data);
    }

    #[Route('/{wikiName}/export', name: 'wiki_export', methods: ['GET'])]
    public function exportAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('manage', $wiki);

        $json = json_encode($wikiService->export($wiki), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $filename = $wiki->getName().'.json';
        $response = new Response($json);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/{wikiName}/export-single-markdown', name: 'wiki_export_single_markdown', methods: ['GET'])]
    public function exportSingleMarkdownAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('manage', $wiki);

        $markdown = $wikiService->renderSingleMarkdown($wiki);

        $response = new Response($markdown);
        $response->headers->set('Content-type', 'text/markdown');

        return $response;
    }

    #[Route('/{wikiName}/export-single-html', name: 'wiki_export_single_html', methods: ['GET'])]
    public function exportSingleHtmlAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('manage', $wiki);

        $markdown = $wikiService->renderSingleMarkdown($wiki);
        $html = $wikiService->markdownToHtml($wiki, $markdown);
        $yaml = $wiki->getConfig() ?? '';
        $config = Yaml::parse($yaml);
        if (isset($config['layout'])) {
            $layout = file_get_contents($config['layout']);
            $html = $wikiService->processTwig($wiki, $layout, ['content' => $html]);
        }

        $response = new Response($html);
        $response->headers->set('Content-type', 'text/html');

        return $response;
    }

    #[Route('/{wikiName}/pull', name: 'wiki_pull', methods: ['GET'])]
    public function gitPullAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('manage', $wiki);

        $wikiService->pull($wiki);

        return $this->redirectToRoute('wiki_view', ['wikiName' => $wiki->getName()]);
    }
}
