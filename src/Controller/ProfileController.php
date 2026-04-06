<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\ChangedUsername;
use App\Activity\Messages\RsvpNo;
use App\Activity\Messages\RsvpYes;
use App\Entity\Event;
use App\Filter\Event\EventFilterService;
use App\Form\ProfileType;
use App\Repository\EventRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\Member\BlockingService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public const string ROUTE_PROFILE = 'app_profile';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly EventRepository $repo,
        private readonly MessageRepository $msgRepo,
        private readonly UserRepository $userRepo,
        private readonly BlockingService $blockingService,
        private readonly EventFilterService $eventFilterService,
    ) {}

    #[Route('/profile/', name: self::ROUTE_PROFILE)]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();
        $oldUserName = $user->getName();

        $form = $this->createForm(ProfileType::class, $this->getAuthedUser());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newUserName = $form->get('name')->getData();
            if ($oldUserName !== $newUserName) {
                $this->activityService->log(ChangedUsername::TYPE, $user, [
                    'old' => $oldUserName,
                    'new' => $newUserName,
                ]);
            }
            $user->setBio($form->get('bio')->getData());
            $user->setLocale($form->get('languages')->getData());
            $user->setPublic($form->get('public')->getData());

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_profile');
        }

        // Apply user-scoped content filtering (group memberships + domain context)
        $filterResult = $this->eventFilterService->getEventIdFilterForUserProfile($user);
        $eventIds = $filterResult->getEventIds();

        return $this->render(
            'profile/index.html.twig',
            [
                'lastLogin' => $request->getSession()->get('lastLogin', '-'),
                'messageCount' => $this->msgRepo->getMessageCount($user),
                'socialCounts' => $this->userRepo->getSocialCounts($user),
                'blockedCount' => count($this->blockingService->getBlockedUsers($user)),
                'user' => $this->getAuthedUser(),
                'upcoming' => $this->repo->getUpcomingEvents(10, $eventIds),
                'past' => $this->repo->getPastAttendedEvents($user, 20, $eventIds),
                'form' => $form,
            ],
            $response,
        );
    }

    #[Route('/profile/toggleRsvp/{event}/', name: 'app_profile_toggle_rsvp')]
    public function toggleRsvp(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($event->getStart() < new DateTimeImmutable()) { // does reload page for flashMessage to trigger
            $this->addFlash('error', 'You cannot RSVP to an event that has already happened.');

            return new Response('', Response::HTTP_LOCKED);
        }

        $user = $this->getAuthedUser();
        $status = $event->toggleRsvp($user);
        $em->persist($event);
        $em->flush();

        $type = $status ? RsvpYes::TYPE : RsvpNo::TYPE;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $status]);
        }

        return $this->redirectToRoute('app_profile');
    }
}
