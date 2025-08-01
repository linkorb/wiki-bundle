<?php

namespace LinkORB\Bundle\WikiBundle\Twig;

use LinkORB\Bundle\WikiBundle\Contracts\MetaEntityServiceInterface;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    /** @var string[] */
    private array $wikiPageMetaUserEntityCache = [];

    public function __construct(
        private readonly MetaEntityServiceInterface $metaEntityService,
        private readonly WikiRepository $wikiRepository,
        private readonly WikiPageRepository $wikiPageRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return list<array{ name: string|null, link: string|null}>
     */
    #[AsTwigFunction('favoriteWikiPagesList')]
    public function favoriteWikiPagesList(): array
    {
        $username = $this->tokenStorage->getToken()?->getUserIdentifier();
        if (!$username) {
            return [];
        }


        $metaUserEntities = $this->metaEntityService->getFavoriteByBusinessKey($username, WikiPage::class, 10);
        if (empty($metaUserEntities)) {
            return [];
        }

        $recentEntities = [];

        foreach ($metaUserEntities as $metaUserEntity) {
                /** @var MetaEntity|null $metaEntity */
                $metaEntity = $metaUserEntity->getMetaEntity();
                $businessKey = preg_replace('/[^0-9]/', '', (string) $metaEntity?->getBusinessKey());
                if (empty($businessKey)) {
                    continue;
                }

            $entity = $this->wikiPageRepository->find($businessKey);
            if (is_null($entity)) {
                continue;
            }

            $recentEntities[] = [
                'name' => $entity->getName(),
                'link' => $this->router->generate(
                    'wiki_page_view',
                    ['wikiName' => $entity->getWiki()?->getName(), 'pageName' => $entity->getName()]
                ),
            ];

        }

        return $recentEntities;
    }

    /**
     * @return list<array{ name: string|null, link: string|null}>
     */
    #[AsTwigFunction('recentWikiPagesList')]
    public function recentWikiPagesList(): ?array
    {
        $username = $this->tokenStorage->getToken()?->getUserIdentifier();
        if (!$username) {
            return [];
        }
        $metaUserEntities = $this->metaEntityService->getUserRecentByBusinessKey($username, WikiPage::class, 5);
        if (empty($metaUserEntities)) {
            return [];
        }

        $recentWikiPages = [];
        foreach ($metaUserEntities as $metaUserEntity) {
            $businessKey = preg_replace('/[^0-9]/', '', (string) $metaUserEntity->getMetaEntity()?->getBusinessKey());
            if (empty($businessKey)) {
                continue;
            }

            $entity = $this->wikiPageRepository->find($businessKey);
            if (is_null($entity)) {
                continue;
            }
            $recentWikiPages[] = [
                'name' => $entity->getName(),
                'link' => $this->router->generate(
                    'wiki_page_view',
                    ['wikiName' => $entity->getWiki()?->getName(), 'pageName' => $entity->getName()]
                ),
            ];
        }

        return $recentWikiPages;
    }


    #[AsTwigFunction('getWikiPageMetaUserEntity')]
    public function getWikiPageMetaUserEntity(?string $wikiName = null, ?string $pageName = null): null|object|string
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user) {
            return null;
        }

        $username = $user->getUserIdentifier();

        // If wikiPageId is provided, use it; otherwise, get from request
        if (null === $pageName && null === $wikiName) {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request || !$request->attributes->has('pageName') || !$request->attributes->has('wikiName')) {
                return null;
            }
            $wikiName = $request->attributes->get('wikiName');
            $pageName = $request->attributes->get('pageName');
        }

        $wiki = $this->wikiRepository->findOneByName($wikiName);
        if (!$wiki) {
            return null;
        }

        $wikiPage = $this->wikiPageRepository->findOneByWikiIdAndName(
            $wiki->getId(),
            $pageName
        );
        if (!$wikiPage) {
            return null;
        }

        $cacheKey = $username.':'.$wikiPage->getId();
        if (isset($this->wikiPageMetaUserEntityCache[$cacheKey])) {
            return $this->wikiPageMetaUserEntityCache[$cacheKey];
        }

        $result = $this->metaEntityService->ensureMetaUserEntity($username, $wikiPage::class.':'.$wikiPage->getId());
        $this->wikiPageMetaUserEntityCache[$cacheKey] = $result;

        return $result;
    }
}
