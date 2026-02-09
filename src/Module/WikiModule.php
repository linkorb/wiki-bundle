<?php

declare(strict_types=1);

namespace LinkORB\Bundle\WikiBundle\Module;

use LinkORB\Bundle\NebulaBundle\Contracts\AbstractModule;
use LinkORB\Bundle\NebulaBundle\Contracts\ModuleConfigLink;
use LinkORB\Bundle\NebulaBundle\Contracts\ModuleEntityLink;
use LinkORB\Bundle\NebulaBundle\Contracts\ModuleStat;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Wiki Module - provides wiki documentation functionality for Nebula.
 *
 * Manages wikis and wiki pages for team documentation.
 */
#[AutoconfigureTag('nebula.module')]
class WikiModule extends AbstractModule
{
    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly WikiPageRepository $wikiPageRepository,
    ) {}

    public function getName(): string
    {
        return 'wiki';
    }

    public function getLabel(): string
    {
        return 'Wiki';
    }

    public function getIcon(): ?string
    {
        return 'icon-lux-article';
    }

    public function getDescription(): string
    {
        return 'Team documentation and knowledge base management';
    }

    public function getRoutePrefix(): string
    {
        return 'wiki';
    }

    /**
     * @return ModuleStat[]
     */
    public function getLanderStats(): array
    {
        $wikiCount = $this->wikiRepository->count([]);
        $pageCount = $this->wikiPageRepository->count([]);

        return [
            new ModuleStat(
                label: 'Wikis',
                value: $wikiCount,
                route: 'wiki_index',
                icon: 'icon-lux-article',
            ),
            new ModuleStat(
                label: 'Pages',
                value: $pageCount,
                route: 'wiki_index',
                icon: 'icon-lux-article',
            ),
        ];
    }

    /**
     * @return ModuleEntityLink[]
     */
    public function getPrimaryEntities(): array
    {
        return [
            new ModuleEntityLink(
                label: 'Wikis',
                route: 'wiki_index',
                icon: 'icon-lux-article',
                description: 'Browse all wikis',
            ),
        ];
    }

    /**
     * @return ModuleConfigLink[]
     */
    public function getConfigRoutes(): array
    {
        return [
            new ModuleConfigLink(
                label: 'Add Wiki',
                route: 'wiki_add',
                icon: 'icon-lux-add',
                description: 'Create a new wiki',
            ),
            new ModuleConfigLink(
                label: 'Search',
                route: 'wiki_search',
                icon: 'icon-lux-search',
                description: 'Search across all wikis',
            ),
        ];
    }
}
