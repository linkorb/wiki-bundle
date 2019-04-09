<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('IS_AUTHENTICATED_FULLY')")
 * @Route("/wiki/{wikiName}/events")
 */
class WikiEventController extends AbstractController
{
    /**
     * @Route("/", name="wiki_event_index")
     */
    public function indexAction($wikiName, WikiRepository $wikiRepository, WikiEventRepository $wikiEventRepository)
    {
        if (!$wiki = $wikiRepository->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }
        $wikiEvents = $wikiEventRepository->findByWikiId($wiki->getId());

        return $this->render('@Wiki/wiki_event/index.html.twig', [
            'wikiEvents' => $wikiEvents,
            'wiki' => $wiki,
        ]);
    }
}
