<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * @Route("/wiki/{wikiName}")
 */
class WikiEventController extends AbstractController
{
    public function __construct(
        private WikiService $wikiService,
        private WikiRepository $wikiRepository,
        private WikiPageRepository $wikiPageRepository,
        private WikiEventRepository $wikiEventRepository
    ) {
    }

    /**
     * @Route("/events", name="wiki_event_index", methods={"GET"})
     */
    public function indexAction(string $wikiName): Response
    {
        if (!$wiki = $this->wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiId($wiki->getId());

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiId($wiki->getId());

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_event/index.html.twig', $data);
    }

    /**
     * @Route("/events/{eventId}", name="wiki_event_view", methods={"GET"})
     */
    public function viewEventAction(string $wikiName, int $eventId): Response
    {
        if (!$wiki = $this->wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiEvent = $this->wikiEventRepository->findOneByWikiIdAndId($wiki->getId(), $eventId)) {
            throw new InvalidArgumentException('Event not found!');
        }
        $data = $wikiRoles;
        $data['wikiEvent'] = $wikiEvent;
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_event/view.html.twig', $data);
    }

    /**
     * @Route("/{pageName}/events", name="wiki_page_event_index", methods={"GET"})
     */
    public function wikiPageEventsAction(string $wikiName, string $pageName): Response
    {
        if (!$wiki = $this->wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }
        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        if (!$wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName($wiki->getId(), $pageName)) {
            throw new InvalidArgumentException('Page not found!');
        }

        $wikiEvents = $this->wikiEventRepository->findByWikiPageId($wikiPage->getId());

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;
        $data['wikiPage'] = $wikiPage;

        return $this->render('@Wiki/wiki_event/page_event.html.twig', $data);
    }
}
