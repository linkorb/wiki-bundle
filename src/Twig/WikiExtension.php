<?php

namespace LinkORB\Bundle\WikiBundle\Twig;

use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Services\WikiPageService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WikiExtension extends AbstractExtension
{
    public function __construct(
        private readonly WikiPageService $wikiPageService,
        private readonly WikiPageRepository $wikiPageRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wikiRecursivePages', $this->wikiRecursivePages(...)),
            new TwigFunction('wikiPageBreadcrumbs', $this->wikiPageBreadcrumbs(...)),
            new TwigFunction('wikiPageByName', $this->wikiPageByName(...)),
        ];
    }

    public function wikiRecursivePages($wikiId)
    {
        $wikiPages = $this->wikiPageService->getByWikiIdAndParentId((int) $wikiId);

        foreach ($wikiPages as $wikiPage) {
            $wikiPage->setChildPages($this->wikiPageService->recursiveChild($wikiPage));
        }

        return $wikiPages;
    }

    public function wikiPageBreadcrumbs($wikiId, $wikiPageId)
    {
        $data = $this->wikiPageService->breadcrumbs((int) $wikiId, (int) $wikiPageId);

        return $data;
    }

    public function wikiPageByName($wikiId, string $pageName)
    {
        return $this->wikiPageRepository->findOneByWikiIdAndName((int) $wikiId, $pageName);
    }
}
