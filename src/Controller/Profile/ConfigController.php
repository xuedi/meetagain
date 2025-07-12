<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Form\ChangePassword;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ConfigController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/profile/config', name: 'app_profile_config')]
    public function config(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(ChangePassword::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            if ($hasher->isPasswordValid($user, $form->get('oldPassword')->getData())) {
                $user->setPassword($hasher->hashPassword($user, $form->get('newPassword')->getData()));

                $this->em->persist($user);
                $this->em->flush();

                $this->addFlash('success', 'Password was changed, please verify by logging in again');
            } else {
                $this->addFlash('error', 'The old password is not correct');
            }
        }
        return $this->render('profile/config.html.twig', [
            'user' => $this->getAuthedUser(),
            'form' => $form,
        ]);
    }

    #[Route('/profile/config/toggle/{type}', name: 'app_profile_config_toggle', requirements: ['type' => 'osm|tagging|notification|public'])]
    public function toggle(Request $request, string $type): Response
    {
        $user = $this->getAuthedUser();
        match ($type) {
            'osm' => $user->setOsmConsent(!$user->isOsmConsent()),
            'tagging' => $user->setTagging(!$user->isTagging()),
            'notification' => $user->setNotification(!$user->isNotification()),
            'public' => $user->setPublic(!$user->isPublic()),
        };
        $this->em->persist($user);
        $this->em->flush();

        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_profile_config');
        }

        return $this->render('_block/toggle.html.twig', [
            'link' => $this->generateUrl('app_profile_config_toggle', ['type' => $type]),
            'status' => match ($type) {
                'osm' => $user->isOsmConsent(),
                'tagging' => $user->isTagging(),
                'notification' => $user->isNotification(),
                'public' => $user->isPublic(),
            },
        ]);
    }

    #[Route('/profile/config/toggleNotification/{type}', name: 'app_profile_config_toggle_notification')]
    public function toggleNotification(Request $request, string $type): Response
    {
        $user = $this->getAuthedUser();
        $user->setNotificationSettings($user->getNotificationSettings()->toggle($type));

        $this->em->persist($user);
        $this->em->flush();

        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_profile_config');
        }

        return $this->render('_block/toggle.html.twig', [
            'link' => $this->generateUrl('app_profile_config_toggle_notification', ['type' => $type]),
            'status' => $user->getNotificationSettings()->isActive($type),
        ]);
    }
}
