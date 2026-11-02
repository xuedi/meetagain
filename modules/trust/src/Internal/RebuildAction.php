<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Trust\Contract\ContextDescriptor;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Override;

readonly class RebuildAction implements ActionInterface
{
    public const string IDENTIFIER = 'trust-rebuild';
    public const string ALL = '*';

    public function __construct(
        private ContextRegistry $registry,
        private ScoreProvider $scoreProvider,
        private TrustGrantRepository $grants,
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
        return 'admin_trust_rebuild.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_trust_rebuild.description';
    }

    #[Override]
    public function getParameters(): array
    {
        $choices = ['admin_trust_rebuild.choice_all' => self::ALL];
        foreach ($this->registry->describeAll() as $descriptor) {
            $choices[$descriptor->label] = $descriptor->context;
        }

        return [
            ActionParameter::choice('context', 'admin_trust_rebuild.param_context', $choices, self::ALL),
            ActionParameter::flag('report_only', 'admin_trust_rebuild.param_report_only'),
        ];
    }

    #[Override]
    public function getFacts(): array
    {
        return [
            'admin_trust_rebuild.fact_contexts' => (string) count($this->registry->describeAll()),
            'admin_trust_rebuild.fact_vouches' => (string) $this->grants->count(),
        ];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $context = $values['context'] ?? null;
        $only = is_string($context) && $context !== self::ALL ? $context : null;
        $reportOnly = (bool) ($values['report_only'] ?? false);

        $contexts = $this->rebuild($only, $reportOnly);
        if ($contexts === []) {
            return $only === null
                ? new Result(Status::warning, 'No contexts are described. Trust is inert until a consumer registers one.')
                : new Result(Status::error, sprintf('No describer claims the context "%s".', $only));
        }

        $status = Status::ok;
        $drifted = [];
        $notes = [];
        foreach ($contexts as $rebuilt) {
            $label = $rebuilt['descriptor']->label;
            if ($rebuilt['drifted']) {
                $drifted[] = $label;
            }
            if ($rebuilt['undeclared'] !== []) {
                $status = Status::warning;
                $notes[] = sprintf('%s recorded but never declared, so they score nothing: %s', $label, implode(', ', $rebuilt['undeclared']));
            }
        }

        if ($reportOnly) {
            $summary = $drifted === []
                ? sprintf('No drift across %d context(s)', count($contexts))
                : sprintf('%d of %d context(s) drifted from the cache: %s', count($drifted), count($contexts), implode(', ', $drifted));
            $status = $drifted === [] ? $status : Status::warning;
        } else {
            $summary = sprintf(
                'Rebuilt %d context(s): %s',
                count($contexts),
                implode(', ', array_map(static fn(array $rebuilt): string => $rebuilt['descriptor']->label, $contexts)),
            );
        }

        return new Result($status, implode('; ', [$summary, ...$notes]));
    }

    /**
     * @return list<array{descriptor: ContextDescriptor, scores: array<int, int>, drifted: bool, undeclared: list<string>}>
     */
    public function rebuild(?string $only, bool $reportOnly): array
    {
        $descriptors = $this->registry->describeAll();
        if ($only !== null) {
            $descriptors = array_values(array_filter($descriptors, static fn(ContextDescriptor $descriptor): bool => $descriptor->context === $only));
        }

        $rebuilt = [];
        foreach ($descriptors as $descriptor) {
            $fresh = $this->scoreProvider->calculate($descriptor->context);
            $drifted = false;
            if ($reportOnly) {
                $drifted = $this->scoreProvider->getMap($descriptor->context) !== $fresh;
            } else {
                $this->scoreProvider->invalidate($descriptor->context);
            }

            $rebuilt[] = [
                'descriptor' => $descriptor,
                'scores' => $fresh,
                'drifted' => $drifted,
                'undeclared' => $this->scoreProvider->findUndeclaredActions($descriptor->context),
            ];
        }

        return $rebuilt;
    }
}
