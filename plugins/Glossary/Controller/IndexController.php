<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\Suggestion;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Repository\GlossaryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * TODO: move logic to service & repo
 */
class IndexController extends AbstractSymfonyController
{
    private GlossaryRepository $repo;
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->em = $doctrine->getManager('emGlossary');
        $this->repo = $this->em->getRepository(Glossary::class);
    }

    #[Route('/glossary', name: 'app_plugin_glossary', methods: [ 'GET'])]
    public function show(Request $request, ?int $id = null): Response
    {
        return $this->render('@Glossary/index.html.twig', [
            'glossaryList' => $this->repo->findBy(['approved' => true], ['phrase' => 'ASC']),
            'newPhrases' => $this->repo->findBy(['approved' => false, 'suggestion' => null], ['phrase' => 'ASC']),
            'sideBar' => 'none',
        ]);
    }

    #[Route('/glossary/edit/{id}', name: 'app_plugin_glossary_edit', methods: [ 'GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        $newGlossary = $this->repo->findOneBy(['id' => $id]);

        $form = $this->createForm(GlossaryType::class, $newGlossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->detach($newGlossary); // dont auto save entity
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $current = $this->repo->findOneBy(['id' => $id]);
            if($current->getPhrase() !== $newGlossary->getPhrase()) {
                $current->addSuggestions(Suggestion::fromJson([
                    'createdBy' => $this->getUser()->getId(),
                    'createdAt' => new DateTimeImmutable(),
                    'field' => 'phrase',
                    'value' => $newGlossary->getPhrase(),
                ]));
            }
            if($current->getPinyin() !== $newGlossary->getPinyin()) {
                $current->addSuggestions(Suggestion::fromJson([
                    'createdBy' => $this->getUser()->getId(),
                    'createdAt' => new DateTimeImmutable(),
                    'field' => 'pinyin',
                    'value' => $newGlossary->getPinyin(),
                ]));
            }
            if($current->getCategory() !== $newGlossary->getCategory()) {
                $current->addSuggestions(Suggestion::fromJson([
                    'createdBy' => $this->getUser()->getId(),
                    'createdAt' => new DateTimeImmutable(),
                    'field' => 'category',
                    'value' => (string)$newGlossary->getCategory()->value,
                ]));
            }

            $this->em->persist($current);
            $this->em->flush();

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->render('@Glossary/edit.html.twig', [
            'glossaryList' => $this->repo->findBy(['approved' => true], ['phrase' => 'ASC']),
            'newPhrases' => $this->repo->findBy(['approved' => false, 'suggestion' => null], ['phrase' => 'ASC']),
            'editItem' => $newGlossary,
            'sideBar' => 'edit',
            'form' => $form,
        ]);
    }

    #[Route('/glossary/new', name: 'app_plugin_glossary_new', methods: [ 'GET', 'POST'])]
    public function new(Request $request): Response
    {
        $glossary = new Glossary();

        $form = $this->createForm(GlossaryType::class, $glossary);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->getUser() instanceof User) {
                throw new AuthenticationException('Only for logged in users');
            }
            $userId = $this->getUser()->getId();

            $glossary->setCreatedBy($userId);
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setApproved(false);

            $this->em->persist($glossary);
            $this->em->flush();

            return $this->redirectToRoute('app_plugin_glossary');
        }

        return $this->render('@Glossary/new.html.twig', [
            'glossaryList' => $this->repo->findBy(['approved' => true], ['phrase' => 'ASC']),
            'newPhrases' => $this->repo->findBy(['approved' => false, 'suggestion' => null], ['phrase' => 'ASC']),
            'sideBar' => 'new',
            'form' => $form,
        ]);
    }

    #[Route('/glossary/approval_list/{id}', name: 'app_plugin_glossary_approval_list', methods: [ 'GET'])]
    public function approvalList(int $id): Response
    {
        return $this->render('@Glossary/approve.html.twig', [
            'glossaryList' => $this->repo->findBy(['approved' => true], ['phrase' => 'ASC']),
            'newPhrases' => $this->repo->findBy(['approved' => false, 'suggestion' => null], ['phrase' => 'ASC']),
            'approvalItem' => $this->repo->findOneBy(['id' => $id]),
            'sideBar' => 'approval',
        ]);
    }

    #[Route('/glossary/approve/{id}', name: 'app_plugin_glossary_approve', methods: [ 'GET'])]
    public function approve(int $id): Response
    {
        // TODO: test if manager
        $item = $this->repo->findOneBy(['id' => $id]);
        $item->setApproved(true);
        $this->em->persist($item);
        $this->em->flush();

        return $this->redirectToRoute('app_plugin_glossary');
    }

    #[Route('/glossary/delete/{id}', name: 'app_plugin_glossary_delete', methods: [ 'GET'])]
    public function delete(int $id): Response
    {
        // TODO: test if manager
        $item = $this->repo->findOneBy(['id' => $id]);

        $this->em->remove($item);;
        $this->em->flush();

        return $this->redirectToRoute('app_plugin_glossary');
    }

    #[Route('/glossary/delete_view/{id}', name: 'app_plugin_glossary_delete_view', methods: [ 'GET'])]
    public function deleteView(int $id): Response
    {
        // TODO: test if manager
        return $this->render('@Glossary/delete.html.twig', [
            'glossaryList' => $this->repo->findBy(['approved' => true], ['phrase' => 'ASC']),
            'newPhrases' => $this->repo->findBy(['approved' => false, 'suggestion' => null], ['phrase' => 'ASC']),
            'sideBar' => 'delete',
            'deleteItem' => $this->repo->findOneBy(['id' => $id]),
        ]);
    }
}
