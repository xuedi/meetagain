<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Item\TypeRegistry;
use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Contract\ContextDescriptor;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ContextDescriber implements ContextDescriberInterface
{
    public function __construct(
        private ContextIndex $index,
        private TypeRegistry $itemTypes,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function describe(string $context): ?ContextDescriptor
    {
        $itemType = $this->index->itemTypeFor($context);

        return $itemType === null ? null : $this->descriptorFor($context, $itemType);
    }

    #[Override]
    public function describeAll(): iterable
    {
        foreach ($this->index->all() as $context => $itemType) {
            yield $this->descriptorFor($context, $itemType);
        }
    }

    private function descriptorFor(string $context, string $itemType): ContextDescriptor
    {
        $labelKey = $this->itemTypes->providerForIncludingInactive($itemType)?->getLabelKey();

        return new ContextDescriptor(
            $context,
            $this->translator->trans('circulation.dashboard_page_title', [
                '%type%' => $labelKey === null ? $itemType : $this->translator->trans($labelKey),
            ]),
            $this->urlGenerator->generate('app_circulation_dashboard', ['itemType' => $itemType, 'tab' => DashboardTab::KEY]),
        );
    }
}
