<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/wiki/{wikiName}/events")
 */
class WikiEventController extends AbstractController
{
    /**
     * @Route("/", name="wiki_event_index")
     */
    public function indexAction(WikiEventRepository $wikiEventRepository, WikiRepository $wikiRepository, $wikiName)
    {
        if (!$wiki = $wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }
        $wikiEvents = $wikiEventRepository->findByWikiId($wiki->getId());

        return $this->render('@wiki_bundle/wiki_event/index.html.twig', [
            'wikiEvents' => $wikiEvents,
            'wiki' => $wiki,
        ]);
    }
}
