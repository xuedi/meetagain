<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Item\Tag\TagService;
use App\Service\Config\LanguageService;
use Plugin\Glossary\Enum\DuplicatePolicy;
use Plugin\Glossary\Form\ImportMappingType;
use Plugin\Glossary\Form\ImportUploadType;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\Service\ImportException;
use Plugin\Glossary\Service\TransferService;
use Plugin\Glossary\ValueObject\ImportFile;
use Plugin\Glossary\ValueObject\ImportMapping;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/glossary')]
final class TransferController extends AbstractGlossaryController
{
    private const string SESSION_KEY = 'glossary_import';

    public function __construct(
        GlossaryService $service,
        private readonly TransferService $transfer,
        private readonly TagService $tagService,
        private readonly LanguageService $languageService,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($service);
    }

    #[Route('/import', name: 'app_plugin_glossary_import', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function upload(Request $request): Response
    {
        $form = $this->createForm(ImportUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $content = $file instanceof UploadedFile ? (string) file_get_contents($file->getPathname()) : '';

            try {
                $this->transfer->parse($content);
                $request->getSession()->set(self::SESSION_KEY, $this->transfer->stash($content, $this->stashId($request)));

                return $this->redirectToRoute('app_plugin_glossary_import_map');
            } catch (ImportException $e) {
                $form->get('file')->addError(new FormError($this->translator->trans($e->getMessage(), $e->parameters)));
            }
        }

        return $this->renderPage('@Glossary/import/upload.html.twig', [
            'form' => $form,
            'rowCap' => TransferService::ROW_CAP,
        ]);
    }

    #[Route('/import/map', name: 'app_plugin_glossary_import_map', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function map(Request $request): Response
    {
        $content = $this->transfer->stashed($this->stashId($request));
        if ($content === null) {
            return $this->redirectToRoute('app_plugin_glossary_import');
        }

        try {
            $file = $this->transfer->parse($content);
        } catch (ImportException) {
            return $this->redirectToRoute('app_plugin_glossary_import');
        }

        $languages = $this->languageService->getAdminFilteredEnabledCodes();
        $form = $this->createForm(ImportMappingType::class, $this->defaultMapping($file, $languages, $request->getLocale()), [
            'columns' => $this->columnLabels($file),
            'tags' => $this->tagService->getAssignableChoices(GlossaryTaggableTypeProvider::ITEM_TYPE, $request->getLocale()),
            'languages' => $languages,
        ]);
        $form->handleRequest($request);
        $mapping = $this->mappingFrom($form->getData());

        if ($form->isSubmitted() && $form->isValid() && $this->clicked($form, 'import')) {
            try {
                $summary = $this->transfer->execute($file, $mapping, $this->getAuthedUser());
                $this->transfer->discard($this->stashId($request));
                $request->getSession()->remove(self::SESSION_KEY);
                $this->addFlash('success', $this->translator->trans('glossary_import.flash_done', [
                    '%created%' => $summary['created'],
                    '%updated%' => $summary['updated'],
                    '%skipped%' => $summary['skipped'],
                ]));
                if ($summary['unknownTags'] !== []) {
                    $this->addFlash('warning', $this->translator->trans('glossary_import.flash_unknown_tags', ['%tags%' => implode(
                        ', ',
                        $summary['unknownTags'],
                    )]));
                }

                return $this->redirectToRoute('app_plugin_glossary', $summary['tagId'] === null ? [] : ['tag' => [$summary['tagId']]]);
            } catch (ImportException $e) {
                $form->addError(new FormError($this->translator->trans($e->getMessage(), $e->parameters)));
            }
        }

        return $this->renderPage('@Glossary/import/map.html.twig', [
            'form' => $form,
            'rowCount' => count($file->rows),
            'preview' => $this->transfer->map($file, $mapping, TransferService::PREVIEW_ROWS),
        ]);
    }

    #[Route('/export', name: 'app_plugin_glossary_export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(Request $request): Response
    {
        $response = new Response($this->transfer->export($this->service->getList(), $request->getLocale()));
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'glossary.txt'));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function stashId(Request $request): string
    {
        return (string) $request->getSession()->get(self::SESSION_KEY, '');
    }

    /** @return list<string> */
    private function columnLabels(ImportFile $file): array
    {
        $first = $file->rows[0] ?? [];
        $labels = [];
        foreach ($file->columns as $index => $name) {
            $sample = mb_strimwidth($first[$index] ?? '', 0, 30, '...');
            $labels[] = sprintf('%d: %s', $index + 1, $name !== '' ? $name : $sample);
        }

        return $labels;
    }

    /**
     * @param list<string> $languages
     * @return array<string, mixed>
     */
    private function defaultMapping(ImportFile $file, array $languages, string $locale): array
    {
        return [
            'termColumn' => 0,
            'definitionColumn' => min(1, count($file->columns) - 1),
            'secondaryColumn' => null,
            'tagsColumn' => $file->tagsColumn,
            'language' => in_array($locale, $languages, true) ? $locale : $this->service->sourceLocale(),
            'targetTag' => null,
            'newTagLabel' => null,
            'duplicatePolicy' => DuplicatePolicy::Skip,
        ];
    }

    /** @param array<string, mixed> $data */
    private function mappingFrom(array $data): ImportMapping
    {
        return new ImportMapping(
            termColumn: (int) ($data['termColumn'] ?? 0),
            definitionColumn: (int) ($data['definitionColumn'] ?? 0),
            secondaryColumn: isset($data['secondaryColumn']) ? (int) $data['secondaryColumn'] : null,
            tagsColumn: isset($data['tagsColumn']) ? (int) $data['tagsColumn'] : null,
            language: (string) ($data['language'] ?? $this->service->sourceLocale()),
            targetTagId: isset($data['targetTag']) ? (int) $data['targetTag'] : null,
            newTagLabel: isset($data['newTagLabel']) ? (string) $data['newTagLabel'] : null,
            duplicatePolicy: $data['duplicatePolicy'] ?? DuplicatePolicy::Skip,
        );
    }

    private function clicked(FormInterface $form, string $button): bool
    {
        $child = $form->get($button);

        return $child instanceof ClickableInterface && $child->isClicked();
    }
}
