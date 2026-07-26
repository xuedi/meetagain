<?php declare(strict_types=1);

namespace App\Twig;

use App\Enum\EventInterval;
use App\Service\Event\RecurrenceDescriber;
use App\Service\Event\RecurrenceResolver;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RecurrenceExtension extends AbstractExtension
{
    public function __construct(
        private readonly RecurrenceResolver $resolver,
        private readonly RecurrenceDescriber $describer,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('recurrence_label', $this->recurrenceLabel(...)),
        ];
    }

    public function recurrenceLabel(
        ?EventInterval $rule,
        ?string $ruleSpec,
        ?DateTimeInterface $anchor = null,
    ): string {
        if (!$rule instanceof EventInterval) {
            return '';
        }

        if (EventInterval::Custom !== $rule) {
            return $this->translator->trans($rule->label());
        }

        $pattern = $this->resolver->resolve($rule, $ruleSpec, $anchor ?? new DateTimeImmutable());

        return $pattern instanceof RecurrencePattern
            ? $this->describer->describe($pattern)
            : $this->translator->trans('admin_event.recurrence_summary_empty');
    }
}
