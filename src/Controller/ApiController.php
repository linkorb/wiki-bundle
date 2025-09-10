<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class ApiController extends AbstractController
{
    private function getJsonResponse(array $data, $code = Response::HTTP_OK): Response
    {
        return JsonResponse::fromJsonString(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $code
        );
    }

    #[Route('/wiki/{wikiName}/export', name: 'wiki_bundle__api_wiki_export', methods: ['GET'])]
    public function wikiExportAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService
    ): Response {
        $this->denyAccessUnlessGranted('manage', $wiki);

        $data = $wikiService->export($wiki);

        return $this->getJsonResponse($data);
    }

    #[Route('/wiki/{wikiName}/import', name: 'wiki_bundle__api_wiki_import', methods: ['GET', 'POST'])]
    public function wikiImportAction(
        Request $request,
        WikiService $wikiService,
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki
    ): Response {
        $this->denyAccessUnlessGranted('manage', $wiki);

        if ($request->isMethod('POST')) {
            if ($jsonContent = json_decode($request->getContent(), true)) {
                $wikiService->import($wiki, $jsonContent);
            }
        }

        return $this->getJsonResponse(['status' => 'ok']);
    }
}
