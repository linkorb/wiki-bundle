<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/wiki/{wikiName}')]
class WikiEventController extends AbstractController
{
    public function __construct(
        private WikiService $wikiService,
        private WikiRepository $wikiRepository,
        private WikiPageRepository $wikiPageRepository,
        private WikiEventRepository $wikiEventRepository,
    ) {
    }

    #[Route('/events', name: 'wiki_event_index', methods: ['GET'])]
    public function indexAction(#[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki): Response
    {
        $wikiEvents = $this->wikiEventRepository->findByWikiId($wiki->getId());

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
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
    ): Response {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
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
    ): Response {
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiPageId($wikiPage->getId());

        usort($wikiEvents, function ($a, $b) {
            return $a->getCreatedAt() < $b->getCreatedAt();
        });

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;
        $data['wikiPage'] = $wikiPage;

        return $this->render('@LinkORBWiki/wiki_event/page_event.html.twig', $data);
    }
}
