<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Form\WikiPageContentType;
use LinkORB\Bundle\WikiBundle\Form\WikiPageType;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiEventService;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/wiki/{wikiName}")
 * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
 */
class WikiPageController extends AbstractController
{
    private $wikiService;

    public function __construct(WikiService $wikiService, WikiPageRepository $wikiPageRepository)
    {
        $this->wikiPageRepository = $wikiPageRepository;
        $this->wikiService = $wikiService;
    }

    /**
     * @Route("/pages", name="wiki_page_index", methods="GET")
     */
    public function indexAction(Wiki $wiki): Response
    {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiPages = $this->wikiPageRepository->findByWikiIdAndParentId($wiki->getId(), 0);

        $data = $wikiRoles;
        $data['wikiPages'] = $this->wikiPageRepository->findByWikiId($wiki->getId());
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_page/index.html.twig', $data);
    }

    /**
     * @Route("/pages/add", name="wiki_page_add", methods="GET|POST")
     */
    public function addAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService): Response
    {
        $wikiPage = new WikiPage();
        $wikiPage->setWiki($wiki);

        if ($pageName = $request->query->get('pageName')) {
            $wikiPage->setName($pageName);
        }
        if ($parentId = $request->query->get('parentId')) {
            $wikiPage->setParentId($parentId);
        }

        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    /**
     * @Route("/{pageName}", name="wiki_page_view", methods="GET")
     */
    public function viewAction(Wiki $wiki, string $pageName): Response
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

        $content = null;

        if ($wikiPage) {
            $content = $wikiPage->getContent();
        }
        // preprocess mediawiki style links (convert mediawiki style links into markdown links)
        preg_match_all('/\[\[(.+?)\]\]/u', $content, $matches);
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
            $content = str_replace('[['.$inner.']]', '['.$label.']('.$link.')', $content);
        }

        $data['content'] = $content;
        $data['pageName'] = $pageName; // in case the page does not yet exist
        $data['wikiPage'] = $wikiPage;
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_page/view.html.twig', $data);
    }

    /**
     * @Route("/pages/{pageName}/advanced", name="wiki_page_edit_advance", methods="GET|POST")
     */
    public function editAdvanceAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
    {
        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    /**
     * @Route("/pages/{pageName}/edit", name="wiki_page_edit", methods="GET|POST")
     */
    public function editAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
    {
        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName);

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wikiPage->getWiki())) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $form = $this->createForm(WikiPageContentType::class, $wikiPage);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $wikiPage->setContent($data['content']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($wikiPage);
            $em->flush();

            return new JsonResponse(['status' => 'success']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $wikiEventService->createEvent(
                'page.updated',
                $wikiPage->getWiki()->getId(),
                json_encode([
                    'updatedAt' => time(),
                    'updatedBy' => $this->getUser()->getUsername(),
                    'name' => $wikiPage->getName(),
                ]),
                $wikiPage->getId()
            );

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
        $data = $data + $wikiRoles;

        return $this->render('@Wiki/wiki_page/edit.html.twig', $data);
    }

    /**
     * @Route("/pages/{pageName}/delete", name="wiki_page_delete", methods="GET")
     */
    public function deleteAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService, $pageName): Response
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
                'deletedBy' => $this->getUser()->getUsername(),
                'name' => $wikiPage->getName(),
            ]),
            $wikiPage->getId()
        );

        $em = $this->getDoctrine()->getManager();
        $em->remove($wikiPage);
        $em->flush();

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

            $em = $this->getDoctrine()->getManager();
            $em->persist($wikiPage);
            $em->flush();

            if ($add) {
                $wikiEventService->createEvent(
                    'page.created',
                    $wikiPage->getWiki()->getId(),
                    json_encode([
                        'createdAt' => time(),
                        'createdBy' => $this->getUser()->getUsername(),
                        'name' => $wikiPage->getName(),
                    ]),
                    $wikiPage->getId()
                );
            } else {
                $wikiEventService->createEvent(
                    'page.updated',
                    $wikiPage->getWiki()->getId(),
                    json_encode([
                        'updatedAt' => time(),
                        'updatedBy' => $this->getUser()->getUsername(),
                        'name' => $wikiPage->getName(),
                    ]),
                    $wikiPage->getId()
                );
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
        $data = $data + $wikiRoles;

        $tocPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), 'toc');
        if ($tocPage) {
            // $data['tocPage'] = $tocPage;
        }

        return $this->render('@Wiki/wiki_page/advanced.html.twig', $data);
    }
}
