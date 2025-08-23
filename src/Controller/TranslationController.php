<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Translation;
use App\Form\TranslationType;
use App\Repository\TranslationRepository;
use App\Service\TranslationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/translation')]
class TranslationController extends AbstractController
{
    public const string ROUTE_MANAGE = 'app_translation';

    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslationRepository $translationRepo,
        private readonly EntityManagerInterface $em,
    )
    {
    }

    #[Route('', name: self::ROUTE_MANAGE)]
    public function index(): Response
    {
        return $this->render('translation/index.html.twig', [
            'translationMatrix' => $this->translationService->getMatrix(),
        ]);
    }

    #[Route('/edit/{id}/{lang}', name: 'app_translation_edit')]
    public function edit(Request $request, int $id, string $lang): Response
    {
        $translation = $this->translationRepo->findOneBy([
            'id' => $id,
            'language' => $lang
        ]);
        if (!$translation instanceof Translation) {
            throw $this->createNotFoundException('Translation not found');
        }

        $form = $this->createForm(TranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $translation->setCreatedAt(new DateTimeImmutable());
            $translation->setUser($this->getAuthedUser());

            $this->em->persist($translation);
            $this->em->flush();

            return $this->redirectToRoute(self::ROUTE_MANAGE);
        }

        return $this->render('translation/edit.html.twig', [
            'translations' => $this->translationRepo->findBy(['placeholder' => $translation->getPlaceholder()]),
            'form' => $form->createView(),
            'item' => $translation,
        ]);
    }
}
