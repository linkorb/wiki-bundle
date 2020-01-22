<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Security("is_granted('IS_AUTHENTICATED_FULLY')")
 * @Route("/wiki/{wikiName}/events")
 */
class WikiEventController extends AbstractController
{
    /**
     * @Route("/", name="wiki_event_index")
     * @Security("has_role('ROLE_SUPERUSER') || has_role('ROLE_WIKI') ")
     */
    public function indexAction($wikiName)
    {
        if (!$wiki = $this->get('LinkORB\Bundle\WikiBundle\Repository\WikiRepository')->findOneByName($wikiName)) {
            return $this->redirectToRoute('wiki_index');
        }
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            throw new AccessDeniedException('Access denied!');
        }
        if (!$wikiRoles['readRole']) {
            throw new AccessDeniedException('Access denied!');
        }

        $wikiEvents = $this->get('LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository')->findByWikiId($wiki->getId());

        return $this->render('@Wiki/wiki_event/index.html.twig', [
            'wikiEvents' => $wikiEvents,
            'wiki' => $wiki,
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
