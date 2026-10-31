<?php declare(strict_types=1);

namespace App\Contribution;

use Module\Proposal\Contract\Change\FieldChange;
use Symfony\Component\Form\FormInterface;

/**
 * A section whose rows are corrected through one Symfony form. The hub renders that form, asks the
 * section what changed, and proposes the answer; a section that does not implement this renders a
 * pane of its own.
 */
interface RowFormInterface extends TargetProviderInterface
{
    /** Change-proposal target type the section's rows are proposed against. */
    public function getTargetType(): string;

    /** FQCN of the Symfony form type that edits one row. */
    public function getFormType(): string;

    /** What the form starts from, detached from the persistence layer, or null when the row is gone. */
    public function draftFor(int|string $id): ?Draft;

    /**
     * What the submitted form proposes against the stored row.
     *
     * @return list<FieldChange>
     */
    public function changesFrom(int|string $id, FormInterface $form): array;
}
