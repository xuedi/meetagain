<?php declare(strict_types=1);

namespace Plugin\Glossary\Contribution;

use App\Contribution\Draft;
use App\Contribution\Entry;
use App\Contribution\RowFormInterface;
use App\Entity\User;
use App\Item\Tag\AssignmentFormHelper;
use App\Review\FieldChange;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Component\Form\FormInterface;

readonly class GlossarySection implements RowFormInterface
{
    public const string TYPE = 'glossary';

    public function __construct(
        private GlossaryService $service,
        private AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function getType(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'glossary.menu_main';
    }

    #[Override]
    public function getIcon(): string
    {
        return 'fa-book';
    }

    #[Override]
    public function listForMember(User $user): array
    {
        $entries = [];
        foreach ($this->service->getList() as $entry) {
            $entries[] = new Entry((int) $entry->getId(), (string) $entry->getPhrase(), $entry->getSecondary());
        }

        return $entries;
    }

    #[Override]
    public function mayTouch(User $user, int|string $id): bool
    {
        return $this->service->get((int) $id) instanceof Glossary;
    }

    #[Override]
    public function getTargetType(): string
    {
        return GlossaryTaggableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function getFormType(): string
    {
        return GlossaryType::class;
    }

    #[Override]
    public function draftFor(int|string $id): ?Draft
    {
        $entry = $this->service->getManaged((int) $id);
        if ($entry === null) {
            return null;
        }

        return new Draft($this->service->draftOf($entry), (string) $entry->getPhrase(), 'glossary.contribution_intro', ['entry_id' => (int) $entry->getId()]);
    }

    #[Override]
    public function changesFrom(int|string $id, FormInterface $form): array
    {
        $submitted = $form->getData();
        $current = $this->service->getManaged((int) $id);
        if (!$submitted instanceof Glossary || $current === null) {
            return [];
        }

        $changes = [
            new FieldChange(GlossaryChangeTarget::FIELD_PHRASE, $current->getPhrase(), $submitted->getPhrase()),
            new FieldChange(GlossaryChangeTarget::FIELD_SECONDARY, $current->getSecondary(), $submitted->getSecondary()),
        ];

        $stored = $current->getDefinitionMap();
        foreach ($submitted->getSubmittedDefinitions() ?? [] as $language => $text) {
            $changes[] = new FieldChange(GlossaryChangeTarget::DEFINITION_PREFIX . $language, $stored[$language] ?? null, $text === '' ? null : $text);
        }

        $changes[] = new FieldChange(
            GlossaryChangeTarget::FIELD_TAG,
            $this->service->encodeTagIds($this->service->getTagIds((int) $id)),
            $this->service->encodeTagIds($this->assignmentFormHelper->extractAssignment($form)),
        );

        return $changes;
    }
}
