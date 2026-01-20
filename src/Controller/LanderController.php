<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\NebulaBundle\Attribute\Breadcrumb;
use LinkORB\Bundle\NebulaBundle\Attribute\NebulaNav;
use LinkORB\Bundle\NebulaBundle\Contracts\Breadcrumb as BreadcrumbType;
use LinkORB\Bundle\WikiBundle\Module\WikiModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
#[Route(path: '/wiki')]
class LanderController extends AbstractController
{
    public function __construct(
        private readonly WikiModule $module,
    ) {}

    #[Route('', name: 'wiki_lander', methods: ['GET'])]
    #[Breadcrumb(
        label: 'Wiki',
        parentRoute: 'home',
        icon: 'icon-lux-article',
        title: 'Wiki',
        type: BreadcrumbType::TYPE_ROOT,
    )]
    #[NebulaNav(template: '@LinkORBWiki/nav/wiki.html.twig')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('access', 'wiki');

        return $this->render('@LinkORBNebula/module/lander.html.twig', [
            'module' => $this->module,
            'stats' => $this->module->getLanderStats(),
            'entities' => $this->module->getPrimaryEntities(),
            'configRoutes' => $this->module->getConfigRoutes(),
        ]);
    }
}
