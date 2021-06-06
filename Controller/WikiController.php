<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Form\WikiType;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiEventService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/wiki")
 */
class WikiController extends AbstractController
{
    /**
     * @Route("", name="wiki_index", methods="GET")
     */
    public function indexAction(WikiRepository $wikiRepository): Response
    {
        $wikis = $wikiRepository->findAll();

        $wikiArray = [];
        foreach ($wikis as $wiki) {
            if ($wikiRoles = $this->getWikiPermission($wiki)) {
                if ($wikiRoles['readRole']) {
                    $wikiArray[] = $wiki;
                }
            }
        }

        usort($wikiArray, function($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });

        return $this->render(
            '@Wiki/wiki/index.html.twig',
            ['wikis' => $wikiArray]
        );
    }

    /**
     * @Route("/add", name="wiki_add", methods="GET|POST")
     */
    public function addAction(Request $request, WikiEventService $wikiEventService): Response
    {
        $wiki = new Wiki();

        return $this->getEditForm($request, $wiki, $wikiEventService);
    }

    /**
     * @Route("/{wikiName}/edit", name="wiki_edit", methods="GET|POST")
     * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
     */
    public function editAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService): Response
    {
        return $this->getEditForm($request, $wiki, $wikiEventService);
    }

    /**
     * @Route("/{wikiName}/delete", name="wiki_delete", methods="GET")
     * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
     */
    public function deleteAction(Request $request, Wiki $wiki, WikiEventService $wikiEventService): Response
    {
        if (count($wiki->getWikiPages())) {
            $this->addFlash('error', 'The wiki cannot be deleted because of having a wiki-page.');
        } else {
            $wikiEventService
                ->createEvent(
                    'wiki.deleted',
                    $wiki->getId(),
                    json_encode([
                        'deletedAt' => time(),
                        'deletedBy' => $this->getUser()->getUsername(),
                        'name' => $wiki->getName(),
                    ])
                );
            $em = $this->getDoctrine()->getManager();
            $em->remove($wiki);
            $em->flush();
        }

        return $this->redirectToRoute('wiki_index');
    }

    protected function getEditForm(Request $request, Wiki $wiki, WikiEventService $wikiEventService)
    {
        $form = $this->createForm(WikiType::class, $wiki);
        $form->handleRequest($request);

        $add = !$wiki->getid();

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($wiki);
            $em->flush();

            if ($add) {
                $wikiEventService->createEvent(
                    'wiki.created',
                    $wiki->getId(),
                    json_encode([
                        'createdAt' => time(),
                        'createdBy' => $this->getUser()->getUsername(),
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
                        'updatedBy' => $this->getUser()->getUsername(),
                        'name' => $wiki->getName(),
                        'description' => $wiki->getDescription(),
                    ])
                );
            }

            return $this->redirectToRoute('wiki_view', ['wikiName' => $wiki->getName() ] );
        }

        return $this->render('@Wiki/wiki/edit.html.twig', [
            'wiki' => $wiki,
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

    /**
     * @Route("/{wikiName}", name="wiki_view", methods="GET")
     * @ParamConverter("wiki", options={"mapping"={"wikiName"="name"}})
     */
    public function view(Wiki $wiki, WikiPageRepository $wikiPageRepository): Response
    {
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        $data = $wikiRoles;
        // $wikiPageRepository = $this->get('LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository');


        $indexPage = $wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), 'index');
        if ($indexPage) {
            return $this->redirectToRoute('wiki_page_view', ['wikiName' => $wiki->getName(), 'pageName' => $indexPage->getName()]);
        }

        $data['wikiPages'] = $wikiPageRepository->findByWikiId($wiki->getId());
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_page/index.html.twig', $data);
    }

}
