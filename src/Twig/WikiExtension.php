<?php

namespace LinkORB\Bundle\WikiBundle\Twig;

use LinkORB\Bundle\WikiBundle\Services\WikiPageService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WikiExtension extends AbstractExtension
{
    private $wikiPageService;

    public function __construct(WikiPageService $wikiPageService)
    {
        $this->wikiPageService = $wikiPageService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wikiRecursivePages', [$this, 'wikiRecursivePages']),
            new TwigFunction('wikiPageBreadcrumbs', [$this, 'wikiPageBreadcrumbs']),
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
}
