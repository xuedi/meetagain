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
    public function toggle(string $type): Response
    {
        $user = $this->getAuthedUser();
        switch ($type) {
            case 'osm':
                $user->setOsmConsent(!$user->isOsmConsent());
                break;
            case 'tagging':
                $user->setTagging(!$user->isTagging());
                break;
            case 'notification':
                $user->setNotification(!$user->isNotification());
                break;
            case 'public':
                $user->setPublic(!$user->isPublic());
                break;
            default:
                throw new Exception('Invalid type');
        }

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_profile_config');
    }

    #[Route('/profile/config/toggleNotification/{type}', name: 'app_profile_config_toggle_notification')]
    public function toggleNotification(string $type): Response
    {
        $user = $this->getAuthedUser();
        $user->setNotificationSettings($user->getNotificationSettings()->toggle($type));

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_profile_config');
    }
}
