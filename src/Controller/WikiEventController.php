<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\AccessControl\EvalInterface;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/wiki/{wikiName}')]
class WikiEventController extends AbstractController
{
    public function __construct(
        private readonly WikiService $wikiService,
        private readonly WikiEventRepository $wikiEventRepository,
    ) {
    }

    #[Route('/events', name: 'wiki_event_index', methods: ['GET'])]
    public function indexAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        EvalInterface $wikiAccess
    ): Response
    {
        $access_control_expression = $wiki->getAccessControlExpression();
        if (!empty($access_control_expression)) {
            if (!$wikiAccess->eval($access_control_expression)) {
                throw $this->createAccessDeniedException();
            }
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiId($wiki->getId());

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw $this->createAccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw $this->createAccessDeniedException('Access denied!');
        }

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/index.html.twig', $data);
    }

    #[Route('/events/{eventId}', name: 'wiki_event_view', methods: ['GET'])]
    public function viewEventAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['eventId' => 'id'])] WikiEvent $wikiEvent,
        EvalInterface $wikiAccess
    ): Response {
        $access_control_expression = $wiki->getAccessControlExpression();
        if (!empty($access_control_expression)) {
            if (!$wikiAccess->eval($access_control_expression)) {
                throw $this->createAccessDeniedException();
            }
        }

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw $this->createAccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw $this->createAccessDeniedException('Access denied!');
        }

        $data = $wikiRoles;
        $data['wikiEvent'] = $wikiEvent;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/view.html.twig', $data);
    }

    #[Route('/{pageName}/events', name: 'wiki_page_event_index', methods: ['GET'])]
    public function wikiPageEventsAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['pageName' => 'name'])] WikiPage $wikiPage,
        EvalInterface $wikiAccess
    ): Response {
        $access_control_expression = $wiki->getAccessControlExpression();
        if (!empty($access_control_expression)) {
            if (!$wikiAccess->eval($access_control_expression)) {
                throw $this->createAccessDeniedException();
            }
        }

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw $this->createAccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw $this->createAccessDeniedException('Access denied!');
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiPageId($wikiPage->getId());

        usort($wikiEvents, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;
        $data['wikiPage'] = $wikiPage;

        return $this->render('@LinkORBWiki/wiki_event/page_event.html.twig', $data);
    }
}
