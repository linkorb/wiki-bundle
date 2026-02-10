<?php

namespace LinkORB\Bundle\WikiBundle\Service;

use LinkORB\Bundle\ResourceBundle\Contracts\ResourceDescriptor;
use LinkORB\Bundle\ResourceBundle\Contracts\ResourceProviderInterface;
use LinkORB\Bundle\ResourceBundle\Contracts\ResourceTemplateDescriptor;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * Exposes wiki pages as resources through the ResourceBundle.
 *
 * URI format: wiki://{wikiName}/{pageName}
 *
 * Access control is delegated to the existing WikiVoter
 * by checking isGranted('access', $wiki).
 */
#[AutoconfigureTag('resource.provider')]
class WikiResourceProvider implements ResourceProviderInterface
{
    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly WikiPageRepository $wikiPageRepository,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {}

    public function getResources(): array
    {
        // Wiki pages are dynamic, no static resources
        return [];
    }

    public function getResourceTemplates(): array
    {
        return [
            new ResourceTemplateDescriptor(
                uriTemplate: 'wiki://{wikiName}/{pageName}',
                name: 'Wiki Page',
                description: 'Content of a wiki page. Provide the wiki name and page name to retrieve the raw markdown page content.',
                mimeType: 'text/markdown',
            ),
        ];
    }

    public function supports(string $uri): bool
    {
        return str_starts_with($uri, 'wiki://');
    }

    public function getResourceContent(string $uri): ?string
    {
        $parsed = $this->parseUri($uri);
        if ($parsed === null) {
            return null;
        }

        [$wikiName, $pageName] = $parsed;

        $wiki = $this->wikiRepository->findOneByName($wikiName);
        if ($wiki === null) {
            return null;
        }

        /** @var int $wikiId */
        $wikiId = $wiki->getId();
        $page = $this->wikiPageRepository->findOneByWikiIdAndName($wikiId, $pageName);
        if ($page === null) {
            return null;
        }

        return $page->getContent();
    }

    public function isAccessGranted(string $uri, TokenInterface $token): bool
    {
        $parsed = $this->parseUri($uri);
        if ($parsed === null) {
            return false;
        }

        [$wikiName] = $parsed;

        $wiki = $this->wikiRepository->findOneByName($wikiName);
        if ($wiki === null) {
            return false;
        }

        // Delegate to the existing WikiVoter via the access decision manager,
        // using the provided token so the correct user context is honored.
        return $this->accessDecisionManager->decide($token, ['access'], $wiki);
    }

    /**
     * Parse a wiki:// URI into [wikiName, pageName].
     *
     * @return array{0: string, 1: string}|null
     */
    private function parseUri(string $uri): ?array
    {
        // Expected format: wiki://{wikiName}/{pageName}
        if (!preg_match('#^wiki://([^/]+)/(.+)$#', $uri, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }
}
