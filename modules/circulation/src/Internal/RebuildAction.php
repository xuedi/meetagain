<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CopyStatus;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\LedgerEntryRepository;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;

readonly class RebuildAction implements ActionInterface
{
    public const string IDENTIFIER = 'circulation-rebuild';
    public const string ALL = '*';

    public function __construct(
        private LedgerReplay $replay,
        private CopyRepository $copies,
        private LedgerEntryRepository $entries,
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::Recalculate;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_circulation_rebuild.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_circulation_rebuild.description';
    }

    #[Override]
    public function getParameters(): array
    {
        $choices = ['admin_circulation_rebuild.choice_all' => self::ALL];
        foreach ($this->copies->findContexts() as $context) {
            $choices[$context] = $context;
        }

        return [
            ActionParameter::choice('context', 'admin_circulation_rebuild.param_context', $choices, self::ALL),
            ActionParameter::flag('report_only', 'admin_circulation_rebuild.param_report_only'),
        ];
    }

    #[Override]
    public function getFacts(): array
    {
        return [
            'admin_circulation_rebuild.fact_copies' => (string) $this->copies->count(),
            'admin_circulation_rebuild.fact_entries' => (string) $this->entries->count(),
        ];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $context = $values['context'] ?? null;
        $reportOnly = (bool) ($values['report_only'] ?? false);
        $rebuilt = $this->rebuild(is_string($context) && $context !== self::ALL ? $context : null, $reportOnly);

        if ($rebuilt['copies'] === 0) {
            return new Result(Status::ok, 'No copies in circulation.');
        }

        $missing = [];
        $fixable = [];
        foreach ($rebuilt['drifted'] as $drift) {
            if ($drift['state'] === null) {
                $missing[] = '#' . $drift['copy']->getId();
            } else {
                $fixable[] = '#' . $drift['copy']->getId();
            }
        }

        $notes = [];
        if ($fixable === []) {
            $notes[] = sprintf('No drift across %d copies', $rebuilt['copies']);
        } elseif ($reportOnly) {
            $notes[] = sprintf('%d of %d copies drifted from the ledger: %s', count($fixable), $rebuilt['copies'], implode(', ', $fixable));
        } else {
            $notes[] = sprintf('Rebuilt %d of %d copies from the ledger: %s', count($fixable), $rebuilt['copies'], implode(', ', $fixable));
        }
        if ($missing !== []) {
            $notes[] = sprintf('%d copies have no ledger entries and were left alone: %s', count($missing), implode(', ', $missing));
        }

        $status = $missing !== [] || $reportOnly && $fixable !== [] ? Status::warning : Status::ok;

        return new Result($status, implode('; ', $notes));
    }

    /**
     * @return array{copies: int, drifted: list<array{copy: Copy, state: ?CopyState, storedHolderId: ?int, storedStatus: CopyStatus}>}
     */
    public function rebuild(?string $context, bool $reportOnly): array
    {
        $states = $this->replay->rebuild($context);
        $copies = $this->copies->findAllOrdered($context);

        $drifted = [];
        foreach ($copies as $copy) {
            $state = $states[(int) $copy->getId()] ?? null;
            if ($state !== null && $state->equals($copy->getHolder()?->getId(), $copy->getHeldSince(), $copy->getStatus())) {
                continue;
            }

            $drifted[] = ['copy' => $copy, 'state' => $state, 'storedHolderId' => $copy->getHolder()?->getId(), 'storedStatus' => $copy->getStatus()];
            if ($state === null || $reportOnly) {
                continue;
            }

            $copy->setHolder($state->holderId === null ? null : $this->em->find(User::class, $state->holderId));
            $copy->setHeldSince($state->heldSince);
            $copy->setStatus($state->status);
        }

        if (!$reportOnly && $drifted !== []) {
            $this->em->flush();
        }

        return ['copies' => count($copies), 'drifted' => $drifted];
    }
}
