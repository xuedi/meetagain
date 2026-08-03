<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Publisher\WellKnown\Registry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WellKnownController extends AbstractController
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    #[Route('/.well-known/{suffix}', name: 'app_well_known', requirements: ['suffix' => '[A-Za-z0-9._\-/]+'], methods: ['GET'])]
    public function wellKnown(string $suffix, Request $request): Response
    {
        return $this->serve($suffix, $request);
    }

    #[Route('/llms.txt', name: 'app_llms_txt', methods: ['GET'])]
    public function llms(Request $request): Response
    {
        return $this->serve('llms.txt', $request);
    }

    #[Route('/security.txt', name: 'app_security_txt_legacy', methods: ['GET'])]
    public function securityTxt(Request $request): Response
    {
        return $this->serve('security.txt', $request);
    }

    private function serve(string $suffix, Request $request): Response
    {
        $document = $this->registry->resolve($suffix, $request);
        if ($document === null) {
            throw $this->createNotFoundException();
        }

        if ($document->redirectTo !== null) {
            return new RedirectResponse($document->redirectTo);
        }

        $response = new Response($document->body, Response::HTTP_OK, ['Content-Type' => $document->contentType]);
        $response->setPublic();
        $response->setMaxAge($document->maxAge);

        return $response;
    }
}
