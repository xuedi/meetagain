<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

readonly class CmsService
{
    public function __construct(
        private Environment $twig,
        private CmsRepository $repo,
        private EventRepository $eventRepo,
    ) {
    }

    public function getSites(): array
    {
        return $this->repo->findAll();
    }

    public function handle(string $locale, string $slug, Response $response): Response
    {
        $cms = $this->repo->findOneBy([
            'slug' => $slug,
            'published' => true,
        ]);

        if ($cms === null) {
            return $this->createNotFoundPage();
        }

        $blocks = $cms->getLanguageFilteredBlockJsonList($locale);
        if ($blocks->count() === 0) {
            return new Response($this->twig->render('cms/204.html.twig', [
                'message' => 'page was found but is has no content in this language',
            ]), Response::HTTP_NO_CONTENT);
        }

        // actual CMS content
        $content = $this->twig->render('cms/index.html.twig', [
            'title' => $cms->getPageTitle($locale) ?? 'No Title set',
            'blocks' => $blocks,
            'events' => $this->eventRepo->getUpcomingEvents(),
        ]);

        $response->setContent($content);
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }

    public function createNotFoundPage(): Response
    {
        return new Response($this->twig->render('cms/404.html.twig', [
            'message' => "These aren't the droids you're looking for!",
        ]), Response::HTTP_NOT_FOUND);
    }
}
