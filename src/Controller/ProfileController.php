<?php declare(strict_types=1);

namespace App\Controller;

use App\Controller\Profile\ImageController;
use App\Entity\ActivityType;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Form\ProfileType;
use App\Repository\EventRepository;
use App\Repository\MessageRepository;
use App\Service\ActivityService;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    public const string ROUTE_PROFILE = 'app_profile';

    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/profile/', name: self::ROUTE_PROFILE)]
    public function index(
        ImageUploadController $imageUploadController,
        Request $request,
        EventRepository $repo,
        MessageRepository $msgRepo,
        ImageService $imageService,
        EntityManagerInterface $entityManager,
    ): Response {
        $response = $this->getResponse();
        $user = $this->getAuthedUser();
        $oldUserName = $user->getName();

        $form = $this->createForm(ProfileType::class, $this->getAuthedUser());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $newUserName = $form->get('name')->getData();
            if ($oldUserName !== $newUserName) {
                $this->activityService->log(ActivityType::ChangedUsername, $user, [
                    'old' => $oldUserName,
                    'new' => $newUserName
                ]);
            }
            $user->setBio($form->get('bio')->getData());
            $user->setLocale($form->get('languages')->getData());
            $user->setPublic($form->get('public')->getData());

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_profile');
        }

        $modal = $imageUploadController->imageReplaceModal('user', $user->getId(), true)->getContent();

        return $this->render('profile/index.html.twig', [
            'modal' => $modal,
            'lastLogin' => $request->getSession()->get('lastLogin', '-'),
            'messageCount' => $msgRepo->getMessageCount($user),
            'user' => $this->getAuthedUser(),
            'upcoming' => $repo->getUpcomingEvents(10),
            'past' => $repo->getPastEvents(20),
            'form' => $form,
        ], $response);
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

        // TODO: to slow, need to save event data first, generate log & notification async
        //$type = $event->hasRsvp($user) ? ActivityType::RsvpYes : ActivityType::RsvpNo;
        //$this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $status]);
        }

        return $this->redirectToRoute('app_profile');
    }
}
