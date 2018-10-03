<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Form\WikiPageType;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Resources\Services\WikiEventService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/wiki/{wikiName}")
 * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
 */
class WikiPageController extends Controller
{
    /**
     * @Route("/pages", name="wiki_page_index", methods="GET")
     */
    public function index(WikiPageRepository $wikiPageRepository, Wiki $wiki): Response
    {
        return $this->render('@wiki_bundle/wiki_page/index.html.twig', [
            'wikiPages' => $wikiPageRepository->findByWikiId($wiki->getId()),
            'wiki' => $wiki,
        ]);
    }

    /**
     * @Route("/pages/add", name="wiki_page_add", methods="GET|POST")
     */
    public function addAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService): Response
    {
        $wikiPage = new WikiPage();
        $wikiPage->setWiki($wiki);

        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    /**
     * @Route("/{pageName}", name="wiki_page_view", methods="GET")
     * @ParamConverter("wikiPage", options={"mapping"={"pageName"="name"}})
     */
    public function viewAction(WikiPage $wikiPage): Response
    {
        return $this->render('@wiki_bundle/wiki_page/view.html.twig', ['wikiPage' => $wikiPage]);
    }

    /**
     * @Route("/pages/{id}/edit", name="wiki_page_edit", methods="GET|POST")
     */
    public function editAction(Request $request, Wiki $wiki, WikiPage $wikiPage, WikiEventService $wikiEventService): Response
    {
        return $this->getEditForm($request, $wikiPage, $wikiEventService);
    }

    /**
     * @Route("/pages/{id}/delete", name="wiki_page_delete", methods="GET")
     */
    public function deleteAction(Request $request, Wiki $wiki, WikiPage $wikiPage, WikiEventService $wikiEventService): Response
    {
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
        $form = $this->createForm(WikiPageType::class, $wikiPage);
        $form->handleRequest($request);

        $add = !$wikiPage->getId();

        if ($form->isSubmitted() && $form->isValid()) {
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

            return $this->redirectToRoute('wiki_page_index', [
                'wikiName' => $wikiPage->getWiki()->getName(),
            ]);
        }

        return $this->render('@wiki_bundle/wiki_page/edit.html.twig', [
            'wikiPage' => $wikiPage,
            'form' => $form->createView(),
        ]);
    }
}
