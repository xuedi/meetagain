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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/profile/', name: 'app_profile')]
    public function index(
        ImageUploadController $imageUploadController,
        Request $request,
        EventRepository $repo,
        MessageRepository $msgRepo,
        ImageService $imageService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getAuthedUser();
        $oldUserName = $user->getName();

        $form = $this->createForm(ProfileType::class, $this->getAuthedUser());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // ensure form has expected type
            $image = null;
            $imageData = $form->get('image')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $imageService->upload($imageData, $this->getAuthedUser(), ImageType::ProfilePicture);
            }

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

            if ($image instanceof Image) {
                $user->setImage($image);
            }

            $entityManager->persist($user);
            $entityManager->flush();
            if ($image instanceof Image) {
                $imageService->createThumbnails($image);
            }

            return $this->redirectToRoute('app_profile');
        }

        $modal = $imageUploadController->modal('user', $user->getId(), true)->getContent();

        return $this->render('profile/index.html.twig', [
            'modal' => $modal,
            'lastLogin' => $request->getSession()->get('lastLogin', '-'),
            'messageCount' => $msgRepo->getMessageCount($user),
            'user' => $this->getAuthedUser(),
            'upcoming' => $repo->getUpcomingEvents(10),
            'past' => $repo->getPastEvents(20),
            'form' => $form,
        ]);
    }

    #[Route('/profile/toggleRsvp/{event}/', name: 'app_profile_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthedUser();
        $event->toggleRsvp($user);
        $em->persist($event);
        $em->flush();

        $type = $event->hasRsvp($user) ? ActivityType::RsvpYes : ActivityType::RsvpNo;
        $this->activityService->log($type, $user, ['event_id' => $event->getId()]);

        return $this->redirectToRoute('app_profile');
    }
}
