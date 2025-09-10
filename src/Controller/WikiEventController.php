<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/wiki/{wikiName}')]
class WikiEventController extends AbstractController
{
    #[Route('/events', name: 'wiki_event_index', methods: ['GET'])]
    public function indexAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiEventRepository $wikiEventRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('access', $wiki);

        $wikiEvents = $wikiEventRepository->findByWikiId($wiki->getId());

        $data = [];
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/index.html.twig', $data);
    }

    #[Route('/events/{eventId}', name: 'wiki_event_view', methods: ['GET'])]
    public function viewEventAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['eventId' => 'id'])] WikiEvent $wikiEvent
    ): Response {
        $this->denyAccessUnlessGranted('access', $wiki);

        $data = [];
        $data['wikiEvent'] = $wikiEvent;
        $data['wiki'] = $wiki;

        return $this->render('@LinkORBWiki/wiki_event/view.html.twig', $data);
    }

    #[Route('/{pageName}/events', name: 'wiki_page_event_index', methods: ['GET'])]
    public function wikiPageEventsAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        #[MapEntity(mapping: ['pageName' => 'name'])] WikiPage $wikiPage,
        WikiEventRepository $wikiEventRepository
    ): Response {
        $this->denyAccessUnlessGranted('access', $wiki);

        $wikiEvents = $wikiEventRepository->findByWikiPageId($wikiPage->getId());

        usort($wikiEvents, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $data = [];
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;
        $data['wikiPage'] = $wikiPage;

        return $this->render('@LinkORBWiki/wiki_event/page_event.html.twig', $data);
    }
}
