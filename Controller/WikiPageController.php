<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Form\WikiPageType;
use LinkORB\Bundle\WikiBundle\Services\WikiEventService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Security("is_granted('IS_AUTHENTICATED_FULLY')")
 * @Route("/wiki/{wikiName}")
 * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
 */
class WikiPageController extends Controller
{
    /**
     * @Route("/pages", name="wiki_page_index", methods="GET")
     */
    public function index(Wiki $wiki): Response
    {
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        $wikiPageRepository = $this->get('LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository');

        $data = $wikiRoles;
        $data['wikiPages'] = $wikiPageRepository->findByWikiId($wiki->getId());
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_page/index.html.twig', $data);
    }

    /**
     * @Route("/pages/add", name="wiki_page_add", methods="GET|POST")
     */
    public function addAction(Request $request, Wiki $wiki): Response
    {
        $wikiPage = new WikiPage();
        $wikiPage->setWiki($wiki);

        return $this->getEditForm($request, $wikiPage, $this->get('LinkORB\Bundle\WikiBundle\Services\WikiEventService'));
    }

    /**
     * @Route("/{pageName}", name="wiki_page_view", methods="GET")
     * @ParamConverter("wikiPage", options={"mapping"={"pageName"="name"}})
     */
    public function viewAction(Wiki $wiki, WikiPage $wikiPage): Response
    {
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $data = $wikiRoles;
        $data['wikiPage'] = $wikiPage;
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_page/view.html.twig', $data);
    }

    /**
     * @Route("/pages/{id}/edit", name="wiki_page_edit", methods="GET|POST")
     */
    public function editAction(Request $request, Wiki $wiki, WikiPage $wikiPage): Response
    {
        return $this->getEditForm($request, $wikiPage, $this->get('LinkORB\Bundle\WikiBundle\Services\WikiEventService'));
    }

    /**
     * @Route("/pages/{id}/delete", name="wiki_page_delete", methods="GET")
     */
    public function deleteAction(Request $request, Wiki $wiki, WikiPage $wikiPage): Response
    {
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $this->get('LinkORB\Bundle\WikiBundle\Services\WikiEventService')->createEvent(
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
        if (!$wikiRoles = $this->getWikiPermission($wikiPage->getWiki())) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['writeRole']) {
            throw new AccessDeniedException('Access denied!');
        }

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

        return $this->render('@Wiki/wiki_page/edit.html.twig', [
            'wikiPage' => $wikiPage,
            'form' => $form->createView(),
        ]);
    }

    protected function getWikiPermission(Wiki $wiki)
    {
        $wikiRoles = ['readRole' => false, 'writeRole' => false];
        $flag = false;

        if ($this->isGranted('ROLE_SUPERUSER')) {
            $wikiRoles['readRole'] = true;
            $wikiRoles['writeRole'] = true;
            $flag = true;
        } else {
            if (!empty($wiki->getReadRole())) {
                $readArray = explode(',', $wiki->getReadRole());
                array_walk($readArray, 'trim');

                foreach ($readArray as $read) {
                    if ($this->isGranted($read)) {
                        $wikiRoles['readRole'] = true;
                        $flag = true;
                    }
                }
            }

            if (!empty($wiki->getWriteRole())) {
                $writeArray = explode(',', $wiki->getWriteRole());
                array_walk($writeArray, 'trim');

                foreach ($writeArray as $write) {
                    if ($this->isGranted($write)) {
                        $flag = true;
                        $wikiRoles['writeRole'] = true;
                    }
                }
            }
        }

        return  $flag ? $wikiRoles : false;
    }
}
