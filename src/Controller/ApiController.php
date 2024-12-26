<?php

namespace LinkORB\Bundle\WikiBundle\Controller;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

    private function getErrorResponse($code, string $message): Response
    {
        $data = [];
        $data['error'] = [
            'code' => $code,
            'message' => $message,
        ];

        return $this->getJsonResponse($data, $code);
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

        return $flag ? $wikiRoles : false;
    }

    /*
    #[Route('/wiki', name: 'wiki_bundle__api_wiki_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        $wikis = $wikiRepository->findAll();

        usort($wikis, function($a, $b) {
            return strcmp($a->getName(), $b->getName());
        });
        $res = [];
        foreach ($wikis as $wiki) {
            if ($wikiRoles = $this->getWikiPermission($wiki)) {
                $res[] = [
                'name' => $wiki->getName(),
               ];
            }
        }
        return $res;
    } */

    #[Route('/wiki/{wikiName}/export', name: 'wiki_bundle__api_wiki_export', methods: ['GET'])]
    public function wikiExportAction(
        #[MapEntity(mapping: ['wikiName' => 'name'])] Wiki $wiki,
        WikiService $wikiService,
    ): Response {
        if (!$wikiRoles = $this->getWikiPermission($wiki)) {
            return $this->getErrorResponse('Access denied!', Response::HTTP_UNAUTHORIZED);
        }
        $data = $wikiService->export($wiki);

        return $this->getJsonResponse($data);
    }

    #[Route('/wiki/{wikiName}/import', name: 'wiki_bundle__api_wiki_import', methods: ['GET', 'POST'])]
    public function wikiImportAction(
        Request $request,
        WikiService $wikiService,
        #[MapEntity(mapping: ['wikiName' => 'name'])] string $wikiName,
    ): Response {
        if (!$wiki = $wikiService->getWikiByName($wikiName)) {
            return $this->getErrorResponse('Wiki not found!', Response::HTTP_NOT_FOUND);
        }

        if ($request->isMethod('POST')) {
            if ($jsonContent = json_decode($request->getContent(), true)) {
                $wikiService->import($wiki, $jsonContent);
            }
        }

        return $this->getJsonResponse(['status' => 'ok']);
    }
}
