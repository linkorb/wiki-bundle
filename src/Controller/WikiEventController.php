<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// @todo remove wikiRoles from controller and templates
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
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $wikiEvents = $this->wikiEventRepository->findByWikiId($wiki->getId());

        $wikiRoles = $this->wikiService->getWikiPermission($wiki);

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/index.html.twig', $data);
    }

    #[Route('/events/{eventId}', name: 'wiki_event_view', methods: ['GET'])]
    public function viewEventAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['eventId' => 'id'])] WikiEvent $wikiEvent
    ): Response {
        $this->denyAccessUnlessGranted('view', $wiki);

        $wikiRoles = $this->wikiService->getWikiPermission($wiki);
        $data = $wikiRoles;
        $data['wikiEvent'] = $wikiEvent;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/view.html.twig', $data);
    }

    #[Route('/{pageName}/events', name: 'wiki_page_event_index', methods: ['GET'])]
    public function wikiPageEventsAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['pageName' => 'name'])] WikiPage $wikiPage
    ): Response {
        $this->denyAccessUnlessGranted('view', $wiki);

        $wikiRoles = $this->wikiService->getWikiPermission($wiki);

        $wikiEvents = $this->wikiEventRepository->findByWikiPageId($wikiPage->getId());

        usort($wikiEvents, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;
        $data['wikiPage'] = $wikiPage;

        return $this->render('@LinkORBWiki/wiki_event/page_event.html.twig', $data);
    }
}
