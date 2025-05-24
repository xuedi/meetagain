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
    #[Route('/profile/config', name: 'app_profile_config')]
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
}
