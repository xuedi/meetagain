<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Translation;
use App\Entity\TranslationSuggestion;
use App\Entity\TranslationSuggestionStatus;
use App\Form\TranslationType;
use App\Repository\TranslationRepository;
use App\Repository\TranslationSuggestionRepository;
use App\Service\TranslationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TranslationController extends AbstractController
{
    public const string ROUTE_MANAGE = 'app_translation';
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslationRepository $translationRepo,
        private readonly EntityManagerInterface $em,
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
    ) {
    }
    #[Route('/translation', name: self::ROUTE_MANAGE)]
    public function index(): Response
    {
        return $this->render('translation/index.html.twig', [
            'translationMatrix' => $this->translationService->getMatrix(),
        ]);
    }
    #[Route('/translation/edit/{id}/{lang}', name: 'app_translation_edit')]
    public function edit(Request $request, int $id, string $lang): Response
    {
        $translation = $this->translationRepo->findOneBy([
            'id' => $id,
            'language' => $lang,
        ]);
        if (!($translation instanceof Translation)) {
            throw $this->createNotFoundException('Translation not found');
        }
        $before = $translation->getTranslation();

        $form = $this->createForm(TranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->getAuthedUser()->hasRole('ROLE_MANAGER')) {
                $translation->setCreatedAt(new DateTimeImmutable());
                $translation->setUser($this->getAuthedUser());

                $this->em->persist($translation);
                $this->em->flush();

                $this->addFlash('success', 'Translation saved');
            } else {
                $this->addTranslationSuggestion($translation, $lang, $before ?? '');
                $this->addFlash('success', 'Suggested translation, waiting for approval');
            }

            return $this->redirectToRoute('app_translation_edit', [
                'id' => $translation->getId(),
                'lang' => $translation->getLanguage(),
            ]);
        }

        return $this->render('translation/edit.html.twig', [
            'translations' => $this->translationRepo->findBy(['placeholder' => $translation->getPlaceholder()]),
            'suggestions' => $this->translationSuggestionRepo->findBy(
                ['translation' => $translation],
                ['createdAt' => 'ASC'],
            ),
            'form' => $form->createView(),
            'lang' => $lang,
            'item' => $translation,
        ]);
    }
    #[Route('/translation/suggestion/deny/{id}', name: 'app_translation_suggestion_deny')]
    public function suggestionDeny(int $id): Response
    {
        $suggestion = $this->getSuggestion($id);
        $suggestion->setApprovedAt(new DateTimeImmutable());
        $suggestion->setApprovedBy($this->getAuthedUser());
        $suggestion->setStatus(TranslationSuggestionStatus::Denied);

        $this->em->persist($suggestion);
        $this->em->flush();

        return $this->redirectToRoute('app_translation_edit', [
            'id' => $suggestion->getTranslation()?->getId(),
            'lang' => $suggestion->getLanguage(),
        ]);
    }
    #[Route('/translation/suggestion/approve/{id}', name: 'app_translation_suggestion_approve')]
    public function suggestionApprove(int $id): Response
    {
        $suggestion = $this->getSuggestion($id);
        $suggestion->setApprovedAt(new DateTimeImmutable());
        $suggestion->setApprovedBy($this->getAuthedUser());
        $suggestion->setStatus(TranslationSuggestionStatus::Approved);

        $translation = $suggestion->getTranslation();
        if (!($translation instanceof Translation)) {
            throw $this->createNotFoundException('Translation not found');
        }
        $translation->setTranslation($suggestion->getSuggestion());

        $this->em->persist($suggestion);
        $this->em->persist($translation);
        $this->em->flush();

        return $this->redirectToRoute('app_translation_edit', [
            'id' => $suggestion->getTranslation()?->getId(),
            'lang' => $suggestion->getLanguage(),
        ]);
    }
    private function addTranslationSuggestion(Translation $translation, string $lang, string $before): void
    {
        $suggestion = new TranslationSuggestion();
        $suggestion->setLanguage($lang);
        $suggestion->setTranslation($translation);
        $suggestion->setPrevious($before);
        $suggestion->setSuggestion($translation->getTranslation());
        $suggestion->setCreatedBy($this->getAuthedUser());
        $suggestion->setCreatedAt(new DateTimeImmutable());
        $suggestion->setStatus(TranslationSuggestionStatus::Requested);

        $this->em->persist($suggestion);
        $this->em->flush();
    }
    private function getSuggestion(int $id): TranslationSuggestion
    {
        if (!$this->getAuthedUser()->hasRole('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('Only managers can approve or deny suggestions');
        }
        $suggestion = $this->translationSuggestionRepo->findOneBy(['id' => $id]);
        if (!($suggestion instanceof TranslationSuggestion)) {
            throw $this->createNotFoundException('Translation suggestion not found');
        }

        return $suggestion;
    }
}
