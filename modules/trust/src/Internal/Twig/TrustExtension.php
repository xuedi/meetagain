<?php declare(strict_types=1);

namespace Module\Trust\Internal\Twig;

use Module\Trust\Contract\TrustInterface;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\AccessResolver;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\RowBuilder;
use Module\Trust\Internal\ScoreProvider;
use Module\Trust\Internal\VouchService;
use Override;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TrustExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly AccessResolver $accessResolver,
        private readonly RowBuilder $rowBuilder,
        private readonly ScoreProvider $scoreProvider,
        private readonly ConfigStore $configStore,
        private readonly VouchService $vouchService,
        private readonly ActionRegistry $actionRegistry,
        private readonly TrustInterface $trust,
        private readonly Environment $twig,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('trust_table', $this->table(...), ['is_safe' => ['html']]),
            new TwigFunction('trust_vouch_control', $this->vouchControl(...), ['is_safe' => ['html']]),
            new TwigFunction('trust_badge', $this->badge(...), ['is_safe' => ['html']]),
            new TwigFunction('trust_info', $this->info(...), ['is_safe' => ['html']]),
            new TwigFunction('trust_explanation', $this->explanation(...), ['is_safe' => ['html']]),
        ];
    }

    public function table(string $context): string
    {
        $viewerId = $this->accessResolver->getViewerId();
        if ($viewerId === null || !$this->registry->exists($context) || !$this->accessResolver->canView($context, $viewerId)) {
            return '';
        }

        $isAdministrator = $this->accessResolver->canAdminister($context, $viewerId);

        return $this->twig->render('@Trust/table.html.twig', [
            'context' => $context,
            'rows' => $this->rowBuilder->build($context, $viewerId, $isAdministrator),
            'isAdministrator' => $isAdministrator,
            'levels' => TrustLevel::cases(),
            'minimum' => $this->configStore->get($context)->minimumToParticipate,
        ]);
    }

    public function info(string $context): string
    {
        $viewerId = $this->accessResolver->getViewerId();
        if ($viewerId === null || !$this->registry->exists($context) || !$this->accessResolver->canView($context, $viewerId)) {
            return '';
        }

        $config = $this->configStore->get($context);

        return $this->twig->render('@Trust/info.html.twig', [
            'levels' => TrustLevel::cases(),
            'config' => $config,
            'actions' => array_values($this->actionRegistry->forContext($context)),
            'minimum' => $config->minimumToParticipate,
        ]);
    }

    public function explanation(string $context, ?int $userId = null): string
    {
        $viewerId = $this->accessResolver->getViewerId();
        if ($viewerId === null || !$this->registry->exists($context)) {
            return '';
        }

        $explanation = $this->trust->getExplanation($context, $userId ?? $viewerId);
        if ($explanation === null) {
            return '';
        }

        return $this->twig->render('@Trust/explanation.html.twig', ['explanation' => $explanation]);
    }

    public function vouchControl(string $context, int $userId): string
    {
        $viewerId = $this->accessResolver->getViewerId();
        if ($viewerId === null || $viewerId === $userId || !$this->registry->exists($context)) {
            return '';
        }
        if (!$this->accessResolver->canView($context, $viewerId)) {
            return '';
        }

        return $this->twig->render('@Trust/vouch_control.html.twig', [
            'context' => $context,
            'userId' => $userId,
            'levels' => TrustLevel::cases(),
            'current' => $this->vouchService->getOutgoing($context, $viewerId)[$userId] ?? null,
        ]);
    }

    public function badge(string $context, int $userId): string
    {
        $viewerId = $this->accessResolver->getViewerId();
        if ($viewerId === null || !$this->registry->exists($context) || !$this->accessResolver->canView($context, $viewerId)) {
            return '';
        }

        $config = $this->configStore->get($context);

        return $this->twig->render('@Trust/badge.html.twig', [
            'band' => $config->bandFor($this->scoreProvider->getMap($context)[$userId] ?? 0),
            'vouches' => $this->vouchService->getVouchCounts($context)[$userId] ?? 0,
        ]);
    }
}
