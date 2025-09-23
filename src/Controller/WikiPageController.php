<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use LinkORB\Bundle\WikiBundle\Contracts\MetaEntityServiceInterface;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Form\WikiPageContentType;
use LinkORB\Bundle\WikiBundle\Form\WikiPageType;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiEventService;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/wiki/{wikiName}')]
class WikiPageController extends AbstractController
{
    #[Route('/pages', name: 'wiki_page_index', methods: ['GET'])]
    public function indexAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiPageRepository $wikiPageRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $data = [];
        $data['wikiPages'] = $wikiPageRepository->findByWikiId($wiki->getId());
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_page/index.html.twig', $data);
    }

    #[Route('/pages/read-only', name: 'wiki_page_read_only', methods: ['GET'])]
    public function readOnlyAction(#[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        return $this->render('@LinkORBWiki/wiki_page/read_only.html.twig', [
            'wiki' => $wiki,
        ]);
    }

    #[Route('/pages/add', name: 'wiki_page_add', methods: ['GET', 'POST'])]
    public function addAction(
        Request $request,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService,
        WikiPageRepository $wikiPageRepository,
        WikiService $wikiService,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('create', 'wiki_pages');

        $wikiPage = new WikiPage();
        $wikiPage->setWiki($wiki);

        if ($wiki->isReadOnly()) {
            return $this->redirectToRoute('wiki_page_read_only', [
                'wikiName' => $wiki->getName(),
            ]);
        }

        if ($pageName = $request->query->get('pageName')) {
            $wikiPage->setName($pageName);
        }
        if ($parentId = $request->query->get('parentId')) {
            $wikiPage->setParentId($parentId);
        }

        return $this->getEditForm($request, $wikiPage, $wikiEventService, $wikiPageRepository, $wikiService, $em);
    }

    #[Route('/{pageName}', name: 'wiki_page_view', methods: ['GET'])]
    public function viewAction(
        Request $request,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        string $pageName,
        WikiPageRepository $wikiPageRepository,
        WikiService $wikiService
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $wikiPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);
        $data = [];

        $tocPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), 'toc');
        if ($tocPage) {
            $data['tocPage'] = $tocPage;
        }

        $markdown = $wikiPage?->getContent();
        $html = $wikiService->markdownToHtml($wiki, $markdown);

        foreach ($request->query->all() as $k => $v) {
            $html = str_replace('{{'.$k.'}}', $v, $html);
        }

        $data['contentHtml'] = $html;
        $data['pageName'] = $pageName; // in case the page does not yet exist
        $data['wikiPage'] = $wikiPage;
        $data['wiki'] = $wiki;

        $wikiService->autoPull($wiki);

        return $this->render('@LinkORBWiki/wiki_page/view.html.twig', $data);
    }

    #[Route('/pages/{pageName}/advanced', name: 'wiki_page_edit_advance', methods: ['GET', 'POST'])]
    public function editAdvanceAction(
        Request $request,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService,
        string $pageName,
        WikiPageRepository $wikiPageRepository,
        WikiService $wikiService,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('modify', $wiki);

        if ($wiki->isReadOnly()) {
            return $this->redirectToRoute('wiki_page_read_only', [
                'wikiName' => $wiki->getName(),
            ]);
        }

        $wikiPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        return $this->getEditForm($request, $wikiPage, $wikiEventService, $wikiPageRepository, $wikiService, $em);
    }

    #[Route('/pages/{pageName}/edit', name: 'wiki_page_edit', methods: ['GET', 'POST'])]
    public function editAction(
        Request $request,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService,
        string $pageName,
        WikiPageRepository $wikiPageRepository,
        WikiService $wikiService,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('modify', $wiki);

        if ($wiki->isReadOnly()) {
            return $this->redirectToRoute('wiki_page_read_only', [
                'wikiName' => $wiki->getName(),
            ]);
        }

        $wikiPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        $wikiPageBeforeTitle = $wikiPage->getName();
        $wikiPageBeforeContent = $wikiPage->getContent();

        $form = $this->createForm(WikiPageContentType::class, $wikiPage);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $wikiPage->setContent($data['content']);

            $em->persist($wikiPage);
            $em->flush();

            $eventData = [
                'updatedAt' => time(),
                'updatedBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                'name' => $wikiPage->getName(),
            ];
            if (0 !== strcmp((string) $wikiPageBeforeContent, (string) $wikiPage->getContent())) {
                $eventData['changes'][] = $wikiEventService->fieldDataChangeArray(
                    'content',
                    $wikiPageBeforeContent,
                    $wikiPage->getContent()
                );
            }

            $wikiEventService->createEvent(
                'page.updated',
                $wikiPage->getWiki()->getId(),
                json_encode($eventData),
                $wikiPage->getId()
            );

            $this->publishPage($wikiPage, $wikiService);

            return new JsonResponse(['status' => 'success']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $eventData = [
                'updatedAt' => time(),
                'updatedBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                'name' => $wikiPage->getName(),
            ];
            if (0 !== strcmp((string) $wikiPageBeforeTitle, (string) $wikiPage->getName())) {
                $eventData['changes'][] = $wikiEventService->fieldDataChangeArray(
                    'title',
                    $wikiPageBeforeTitle,
                    $wikiPage->getName()
                );
            }
            if (0 !== strcmp((string) $wikiPageBeforeContent, (string) $wikiPage->getContent())) {
                $eventData['changes'][] = $wikiEventService->fieldDataChangeArray(
                    'content',
                    $wikiPageBeforeContent,
                    $wikiPage->getContent()
                );
            }

            $wikiEventService->createEvent(
                'page.updated',
                $wikiPage->getWiki()->getId(),
                json_encode($eventData),
                $wikiPage->getId()
            );

            $this->publishPage($wikiPage, $wikiService);

            return $this->redirectToRoute('wiki_page_view', [
                'wikiName' => $wikiPage->getWiki()->getName(),
                'pageName' => $wikiPage->getName(),
            ]);
        }

        $data = [
            'wikiPage' => $wikiPage,
            'wiki' => $wiki,
            'form' => $form->createView(),
        ];

        return $this->render('@LinkORBWiki/wiki_page/edit.html.twig', $data);
    }


    #[Route('/pages/{pageName}/toggle-favorite', name: 'wiki_page_toggle_favorite', methods: ['GET'])]
    public function toggleFavoriteAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        string $pageName,
        WikiPageRepository $wikiPageRepository,
        MetaEntityServiceInterface $metaEntityService
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $wikiPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);
        if (!$wikiPage) {
            throw $this->createAccessDeniedException('Page not found!');
        }

        $username = $this->getUser()->getUserIdentifier();
        $metaEntityService->toggleFavorite($username, $wikiPage::class.':'.$wikiPage->getId());

        return $this->redirectToRoute('wiki_page_view', [
            'wikiName' => $wiki->getName(),
            'pageName' => $pageName,
        ]);
    }

    #[Route('/pages/{pageName}/delete', name: 'wiki_page_delete', methods: ['POST'])]
    public function deleteAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventService $wikiEventService,
        string $pageName,
        Request $request,
        WikiPageRepository $wikiPageRepository,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('delete', $wiki);

        if (!$this->isCsrfTokenValid('delete-item', (string) $request->getPayload()->get('token'))) {
            throw new BadRequestHttpException('CSRF token invalid!');
        }

        $wikiPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        $wikiEventService->createEvent(
            'page.deleted',
            $wikiPage->getWiki()->getId(),
            json_encode([
                'deletedAt' => time(),
                'deletedBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                'name' => $wikiPage->getName(),
            ]),
            $wikiPage->getId()
        );

        $em->remove($wikiPage);
        $em->flush();

        return $this->redirectToRoute('wiki_page_index', [
            'wikiName' => $wiki->getName(),
        ]);
    }

    protected function getEditForm(
        $request,
        $wikiPage,
        WikiEventService $wikiEventService,
        WikiPageRepository $wikiPageRepository,
        WikiService $wikiService,
        EntityManagerInterface $em
    ): RedirectResponse|Response
    {
        $wikiPageBeforeTitle = $wikiPage->getName();
        $wikiPageBeforeContent = $wikiPage->getContent();

        $form = $this->createForm(WikiPageType::class, $wikiPage);
        $form->handleRequest($request);

        $add = !$wikiPage->getId();

        if ($form->isSubmitted() && $form->isValid()) {
            $wikiPage->setParentId((int) $wikiPage->getParentId());

            if ($add) {
                if ($pageTemplateId = (int) $form->get('page_template')->getData()) {
                    if ($wikiPageTemplate = $wikiPageRepository->find($pageTemplateId)) {
                        $wikiPage->setContent($wikiPageTemplate->getContent());
                    }
                }
            }
            $em->persist($wikiPage);
            $em->flush();

            $eventData = [
                'createdAt' => time(),
                'createdBy' => $this->getUser() ? $this->getUser()->getUserIdentifier() : '',
                'name' => $wikiPage->getName(),
            ];
            if (0 !== strcmp((string) $wikiPageBeforeTitle, (string) $wikiPage->getName())) {
                $eventData['changes'][] = $wikiEventService->fieldDataChangeArray(
                    'title',
                    $wikiPageBeforeTitle,
                    $wikiPage->getName()
                );
            }
            if (0 !== strcmp((string) $wikiPageBeforeContent, (string) $wikiPage->getContent())) {
                $eventData['changes'][] = $wikiEventService->fieldDataChangeArray(
                    'content',
                    $wikiPageBeforeContent,
                    $wikiPage->getContent()
                );
            }

            $wikiEventService->createEvent(
                $add ? 'page.created' : 'page.updated',
                $wikiPage->getWiki()->getId(),
                json_encode($eventData),
                $wikiPage->getId()
            );

            $this->publishPage($wikiPage, $wikiService);

            if ($add) {
                return $this->redirectToRoute('wiki_page_edit', [
                    'wikiName' => $wikiPage->getWiki()->getName(),
                    'pageName' => $wikiPage->getName(),
                ]);
            }

            return $this->redirectToRoute('wiki_page_view', [
                'wikiName' => $wikiPage->getWiki()->getName(),
                'pageName' => $wikiPage->getName(),
            ]);
        }

        $wiki = $wikiPage->getWiki();
        $data = [
            'wikiPage' => $wikiPage,
            'wiki' => $wiki,
            'form' => $form->createView(),
        ];

        return $this->render('@LinkORBWiki/wiki_page/advanced.html.twig', $data);
    }

    private function publishPage(WikiPage $wikiPage, WikiService $wikiService): void
    {
        $wikiService->publishWikiPage(
            $wikiPage->getWiki(),
            $wikiPage,
            $this->getUser()->getUserIdentifier(),
            $this->getUser()->getEmail()
        );
    }
}
