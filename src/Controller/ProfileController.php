<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\UserActivity;
use App\Form\ChangePassword;
use App\Form\ProfileType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\ActivityService;
use App\Service\FriendshipService;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    #[Route('/', name: 'app_profile')]
    public function index(
        Request $request,
        EventRepository $repo,
        UploadService $uploadService,
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
                $image = $uploadService->upload($imageData, $this->getAuthedUser());
            }

            $newUserName = $form->get('name')->getData();
            if ($oldUserName !== $newUserName) {
                $message = sprintf("Changed username from '%s' to '%s'", $oldUserName, $newUserName);
                $this->activityService->log(UserActivity::ChangedUsername, $user, ['old' => $oldUserName, 'new' => $newUserName]);
            }
            // TODO: add following to the form so it is handled automatically
            $user->setBio($form->get('bio')->getData());
            $user->setLocale($form->get('languages')->getData());
            $user->setPublic($form->get('public')->getData());


            if ($image instanceof Image) {
                $user->setImage($image);
            }

            $entityManager->persist($user);
            $entityManager->flush();
            if ($image instanceof Image) {
                $uploadService->createThumbnails($image, [[400, 400]]);
            }

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'lastLogin' => $request->getSession()->get('lastLogin', '-'),
            'user' => $this->getAuthedUser(),
            'upcoming' => $repo->getUpcomingEvents(10),
            'past' => $repo->getPastEvents(20),
            'form' => $form,
        ]);
    }

    #[Route('/toggleRsvp/{event}/', name: 'app_profile_toggle_rsvp')]
    public function toggleRsvp(Event $event, EntityManagerInterface $em): Response
    {
        $user = $this->getAuthedUser();
        $event->toggleRsvp($user);
        $em->persist($event);
        $em->flush();

        if ($event->hasRsvp($user)) {
            $this->activityService->log(UserActivity::RsvpYes, $user, ['event_id' => $event->getId()]);
        } else {
            $this->activityService->log(UserActivity::RsvpNo, $user, ['event_id' => $event->getId()]);
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/messages', name: 'app_profile_messages')]
    public function messages(UserRepository $repo): Response
    {
        return $this->render('profile/messages.html.twig', [
            'friends' => $repo->getFriends($this->getAuthedUser()),
            'user' => $this->getAuthedUser(),
        ]);
    }

    #[Route('/social', name: 'app_profile_social')]
    public function social(UserRepository $repo, string $show = 'friends'): Response
    {
        return $this->render('profile/social.html.twig', [
            'followers' => $repo->getFollowers($this->getAuthedUser(), true),
            'following' => $repo->getFollowing($this->getAuthedUser(), true),
            'friends' => $repo->getFriends($this->getAuthedUser()),
            'activities' => $this->activityService->getUserList($this->getAuthedUser()),
            'user' => $this->getAuthedUser(),
            'show' => $show,
        ]);
    }

    #[Route('/social/friends/', name: 'app_profile_social_friends')]
    public function socialFriends(UserRepository $repo): Response
    {
        return $this->social($repo, 'friends');
    }

    #[Route('/social/toggleFollow/{id}/', name: 'app_profile_social_toggle_follow')]
    public function toggleFollow(FriendshipService $service, int $id): Response
    {
        return $service->toggleFollow($id, 'app_profile_social');
    }

    #[Route('/config', name: 'app_profile_config')]
    public function config(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ChangePassword::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            if ($hasher->isPasswordValid($user, $form->get('oldPassword')->getData())) {
                $user->setPassword($hasher->hashPassword($user, $form->get('newPassword')->getData()));

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Password was changed, please verify by logging in again');
            } else {
                $this->addFlash('error', 'The old password does not match');
            }
        }
        return $this->render('profile/config.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/images', name: 'app_profile_images')]
    public function images(UserRepository $repo): Response
    {
        return $this->render('profile/images.html.twig', [
            //
        ]);
    }
}
