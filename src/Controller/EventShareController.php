<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Service\Event\ShareService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event/{id}/share', requirements: ['id' => '\d+'], methods: ['GET'])]
final class EventShareController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $repo,
        private readonly EventFilterService $eventFilterService,
        private readonly ShareService $shareService,
    ) {}

    #[Route('', name: 'app_event_share')]
    public function page(Request $request, int $id): Response
    {
        $event = $this->findAccessibleEvent($id);
        $locale = $request->getLocale();

        if ($event->findTranslation($locale) === null) {
            return $this->redirectToRoute('app_event');
        }

        return $this->render(
            'events/share.html.twig',
            [
                'event' => $event,
                'share' => $this->shareService->buildSheet($event, $locale),
            ],
            $this->getResponse(),
        );
    }

    #[Route('/modal', name: 'app_event_share_modal')]
    public function modal(Request $request, int $id): Response
    {
        $event = $this->findAccessibleEvent($id);

        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_event_share', ['id' => $id]);
        }

        $locale = $request->getLocale();
        if ($event->findTranslation($locale) === null) {
            return $this->redirectToRoute('app_event');
        }

        $response = new Response($this->renderView('events/_share_sheet.html.twig', [
            'event' => $event,
            'share' => $this->shareService->buildSheet($event, $locale),
            'inModal' => true,
        ]));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    #[Route('/qr.png', name: 'app_event_share_qr')]
    public function qr(Request $request, int $id): Response
    {
        $event = $this->findAccessibleEvent($id);

        $response = new Response(
            $this->shareService->buildQrPng($event, $request->getLocale()),
            Response::HTTP_OK,
            ['Content-Type' => 'image/png'],
        );
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('attachment', sprintf('event-%d-qr.png', $id)),
        );
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function findAccessibleEvent(int $id): Event
    {
        if (!$this->eventFilterService->isEventAccessible($id)) {
            throw $this->createNotFoundException();
        }

        $event = $this->repo->findOneForDetails($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException();
        }

        return $event;
    }
}
