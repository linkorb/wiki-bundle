<?php

namespace LinkORB\Bundle\WikiBundle\Resources\Services;

use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class WikiEventService
{
    private $wikiEventRepository;
    private $tokenStorage;
    private $em;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $em, WikiEventRepository $wikiEventRepository)
    {
        $this->tokenStorage = $tokenStorage;
        $this->wikiEventRepository = $wikiEventRepository;
        $this->em = $em;
    }

    public function createEvent($type, $wikiId, $data, $wikiPageId = null)
    {
        $user = $this->tokenStorage->getToken()->getUser();

        $wikiEvent = new WikiEvent();
        $wikiEvent
            ->setCreatedAt(time())
            ->setCreatedBy($user->getUsername())
            ->setType($type)
            ->setWikiId($wikiId)
            ->setWikiPageId($wikiPageId)
            ->setData($data)
        ;

        $this->em->persist($wikiEvent);
        $this->em->flush();

        return $wikiEvent;
    }
}
