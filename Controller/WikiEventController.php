<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/wiki/{wikiName}/events")
 */
class WikiEventController extends AbstractController
{
    private $wikiService;

    public function __construct(WikiService $wikiService)
    {
        $this->wikiService = $wikiService;
    }

    /**
     * @Route("", name="wiki_event_index")
     */
    public function indexAction($wikiName, WikiRepository $wikiRepository, WikiEventRepository $wikiEventRepository)
    {
        if (!$wiki = $wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }

        $wikiEvents = $wikiEventRepository->findByWikiId($wiki->getId());

        if (!$wikiRoles = $this->wikiService->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiEvents = $wikiEventRepository->findByWikiId($wiki->getId());

        $data = $wikiRoles;
        $data['wikiEvents'] = $wikiEvents;
        $data['wiki'] = $wiki;

        return $this->render('@Wiki/wiki_event/index.html.twig', $data);
    }
}
