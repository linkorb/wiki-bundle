<?php

namespace LinkORB\Bundle\WikiBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use LinkORB\Bundle\WikiBundle\Entity\WikiEvent;
use LinkORB\Bundle\WikiBundle\Repository\WikiEventRepository;
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
        $username = ($this->tokenStorage->getToken())
           ? $this->tokenStorage->getToken()->getUser()->getUsername()
           : '';

        $wikiEvent = new WikiEvent();
        $wikiEvent
            ->setCreatedAt(time())
            ->setCreatedBy($username)
            ->setType($type)
            ->setWikiId($wikiId)
            ->setWikiPageId($wikiPageId)
            ->setData($data);

        $this->em->persist($wikiEvent);
        $this->em->flush();

        return $wikiEvent;
    }

    public function fieldDataChangeArray(string $field, ?string $before = null, ?string $after = null)
    {
        return [
            'field' => $field,
            'before' => $before,
            'after' => $after,
        ];
    }
}
