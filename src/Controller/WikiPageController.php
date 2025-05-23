<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/wiki/{wikiName}')]
class WikiPageController extends AbstractController
{
    public function __construct(
        private readonly WikiService $wikiService,
        private readonly WikiPageRepository $wikiPageRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/pages', name: 'wiki_page_index', methods: ['GET'])]
    public function indexAction(#[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki): Response
    {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }

        $data = $wikiRoles;
        $data['wikiPages'] = $this->wikiPageRepository->findByWikiId($wiki->getId());
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_page/index.html.twig', $data);
    }

    #[Route('/pages/read-only', name: 'wiki_page_read_only', methods: ['GET'])]
    public function readOnlyAction(#[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki): Response
    {
        return $this->render('@LinkORBWiki/wiki_page/read_only.html.twig', [
            'wiki' => $wiki,
        ]);
    }

    #[Route('/pages/add', name: 'wiki_page_add', methods: ['GET', 'POST'])]
    public function addAction(Request $request, #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, WikiEventService $wikiEventService): Response
    {
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

        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    #[Route('/{pageName}', name: 'wiki_page_view', methods: ['GET'])]
    public function viewAction(Request $request, #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, string $pageName): Response
    {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }
        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        $data = $wikiRoles;

        $tocPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), 'toc');
        if ($tocPage) {
            $data['tocPage'] = $tocPage;
        }

        $markdown = $wikiPage?->getContent();
        $html = $this->wikiService->markdownToHtml($wiki, $markdown);

        foreach ($request->query->all() as $k => $v) {
            $html = str_replace('{{'.$k.'}}', $v, $html);
        }

        $data['contentHtml'] = $html;
        $data['pageName'] = $pageName; // in case the page does not yet exist
        $data['wikiPage'] = $wikiPage;
        $data['wiki'] = $wiki;

        $this->wikiService->autoPull($wiki);

        return $this->render('@LinkORBWiki/wiki_page/view.html.twig', $data);
    }

    #[Route('/pages/{pageName}/advanced', name: 'wiki_page_edit_advance', methods: ['GET', 'POST'])]
    public function editAdvanceAction(Request $request, #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
    {
        if ($wiki->isReadOnly()) {
            return $this->redirectToRoute('wiki_page_read_only', [
                'wikiName' => $wiki->getName(),
            ]);
        }

        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    #[Route('/pages/{pageName}/edit', name: 'wiki_page_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
    {
        if ($wiki->isReadOnly()) {
            return $this->redirectToRoute('wiki_page_read_only', [
                'wikiName' => $wiki->getName(),
            ]);
        }

        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wikiPage->getWiki())) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiPageBeforeTitle = $wikiPage->getName();
        $wikiPageBeforeContent = $wikiPage->getContent();

        $form = $this->createForm(WikiPageContentType::class, $wikiPage);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $wikiPage->setContent($data['content']);

            $this->em->persist($wikiPage);
            $this->em->flush();

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

            $this->publishPage($wikiPage);

            return new JsonResponse(['status' => 'success']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

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

            $this->publishPage($wikiPage);

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
        $data += $wikiRoles;

        return $this->render('@LinkORBWiki/wiki_page/edit.html.twig', $data);
    }

    #[Route('/pages/{pageName}/delete', name: 'wiki_page_delete', methods: ['GET'])]
    public function deleteAction(Request $request, #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
    {
        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }

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

        $this->em->remove($wikiPage);
        $this->em->flush();

        return $this->redirectToRoute('wiki_page_index', [
            'wikiName' => $wiki->getName(),
        ]);
    }

    protected function getEditForm($request, $wikiPage, WikiEventService $wikiEventService)
    {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wikiPage->getWiki())) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }
        $wikiPageBeforeTitle = $wikiPage?->getName();
        $wikiPageBeforeContent = $wikiPage?->getContent();

        $form = $this->createForm(WikiPageType::class, $wikiPage);
        $form->handleRequest($request);

        $add = !$wikiPage->getId();

        if ($form->isSubmitted() && $form->isValid()) {
            $wikiPage->setParentId((int) $wikiPage->getParentId());

            if ($add) {
                if ($pageTemplateId = (int) $form->get('page_template')->getData()) {
                    if ($wikiPageTemplate = $this->wikiPageRepository->find($pageTemplateId)) {
                        $wikiPage->setContent($wikiPageTemplate->getContent());
                    }
                }
            }
            $this->em->persist($wikiPage);
            $this->em->flush();

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

            $this->publishPage($wikiPage);

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
        $data += $wikiRoles;

        $tocPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), 'toc');
        if ($tocPage) {
            // $data['tocPage'] = $tocPage;
        }

        return $this->render('@LinkORBWiki/wiki_page/advanced.html.twig', $data);
    }

    private function publishPage(WikiPage $wikiPage)
    {
        return $this->wikiService->publishWikiPage(
            $wikiPage->getWiki(),
            $wikiPage,
            $this->getUser()->getUserIdentifier(),
            $this->getUser()->getEmail()
        );
    }
}
