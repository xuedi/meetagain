<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Exception\Event\RsvpRefusedException;
use App\Filter\Event\EventFilterService;
use App\Form\ProfileType;
use App\Service\Event\EventService;
use App\Service\Event\RsvpService;
use App\Service\Member\BlockingService;
use App\Service\Member\FriendshipService;
use App\Service\Member\MessageService;
use App\Service\Member\ProfileService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public const string ROUTE_PROFILE = 'app_profile';

    public function __construct(
        private readonly EventService $eventService,
        private readonly MessageService $messageService,
        private readonly FriendshipService $friendshipService,
        private readonly BlockingService $blockingService,
        private readonly EventFilterService $eventFilterService,
        private readonly ProfileService $profileService,
    ) {}

    #[Route('/profile/', name: self::ROUTE_PROFILE)]
    public function index(Request $request): Response
    {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();

        $form = $this->createForm(ProfileType::class, $this->getAuthedUser());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->profileService->update(
                $user,
                (string) $form->get('name')->getData(),
                $form->get('bio')->getData(),
                (string) $form->get('languages')->getData(),
                (bool) $form->get('public')->getData(),
            );

            return $this->redirectToRoute('app_profile');
        }

        $filterResult = $this->eventFilterService->getEventIdFilterForUserProfile($user);
        $eventIds = $filterResult->getEventIds();

        return $this->render(
            'profile/index.html.twig',
            [
                'lastLogin' => $request->getSession()->get('lastLogin', null),
                'messageCount' => $this->messageService->getMessageCount($user),
                'socialCounts' => $this->friendshipService->getSocialCounts($user),
                'blockedCount' => count($this->blockingService->getBlockedUsers($user)),
                'user' => $this->getAuthedUser(),
                'upcoming' => $this->eventService->getUpcomingEvents(10, $eventIds),
                'past' => $this->eventService->getPastAttendedEvents($user, 20, $eventIds),
                'form' => $form,
            ],
            $response,
        );
    }

    #[Route('/profile/toggleRsvp/{event}/', name: 'app_profile_toggle_rsvp', methods: ['POST'])]
    public function toggleRsvp(Request $request, Event $event, RsvpService $rsvpService): Response
    {
        if (!$this->isCsrfTokenValid('app_profile_toggle_rsvp' . $event->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        try {
            $status = $rsvpService->toggle($event, $this->getAuthedUser());
        } catch (RsvpRefusedException $refused) { // does reload page for flashMessage to trigger
            $this->addFlash($refused->reason->flashLevel(), $refused->reason->flashKey());

            return new Response('', Response::HTTP_LOCKED);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $status]);
        }

        return $this->redirectToRoute('app_profile');
    }
}
