<?php declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\AbstractController;
use App\Form\ChangePassword;
use Doctrine\ORM\EntityManagerInterface;
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
                $this->addFlash('error', 'The old password does not match');
            }
        }
        return $this->render('profile/config.html.twig', [
            'user' => $this->getAuthedUser(),
            'form' => $form,
        ]);
    }

    #[Route('/profile/config/toggleVisibility', name: 'app_profile_config_toggle_visibility')]
    public function toggleVisibility(): Response
    {
        $user = $this->getAuthedUser();
        $user->setPublic(!$user->isPublic());

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_profile_config');
    }

    #[Route('/profile/config/toggleOsm', name: 'app_profile_config_toggle_osm')]
    public function toggleOsm(): Response
    {
        $user = $this->getAuthedUser();
        $user->setOsmConsent(!$user->isOsmConsent());

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_profile_config');
    }

    #[Route('/profile/config/toggleTagging', name: 'app_profile_config_toggle_tagging')]
    public function toggleTagging(): Response
    {
        $user = $this->getAuthedUser();
        $user->setTagging(!$user->isTagging());

        $this->em->persist($user);
        $this->em->flush();

        return $this->redirectToRoute('app_profile_config');
    }
}
