<?php declare(strict_types=1);

namespace Plugin\Fediverse\Publisher\PluginSettings;

use App\Admin\Top\Actions\AdminTopActionForm;
use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\TopActionsInterface;
use Plugin\Fediverse\Controller\AdminController;
use Plugin\Fediverse\Form\ConfigType;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ConfigDescriptor implements DescriptorInterface, TopActionsInterface
{
    public function __construct(
        private AccountRepository $accounts,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function getKey(): string
    {
        return ConfigService::KEY;
    }

    public function getPluginKey(): string
    {
        return 'fediverse';
    }

    public function isScopable(): bool
    {
        return true;
    }

    public function getTitleKey(): string
    {
        return 'fediverse_config.page_title';
    }

    public function getIntroKey(): ?string
    {
        return 'fediverse_config.intro';
    }

    public function getFormType(): string
    {
        return ConfigType::class;
    }

    public function getFormOptions(object $data, ?string $scopeId): array
    {
        return ['scope_id' => $scopeId];
    }

    public function createDefault(): object
    {
        return new Config();
    }

    public function applyForm(object $data, FormInterface $form): void {}

    public function getPriority(): int
    {
        return 0;
    }

    public function getTopActions(): array
    {
        if ($this->accounts->countLive() === 0) {
            return [];
        }

        return [
            new AdminTopActionForm(
                label: $this->translator->trans('fediverse_config.stop_button'),
                target: $this->urlGenerator->generate('app_admin_plugin_fediverse_stop'),
                csrfTokenId: AdminController::STOP_TOKEN,
                icon: 'trash',
                variant: 'is-danger',
                confirm: $this->translator->trans('fediverse_config.stop_confirm'),
            ),
        ];
    }
}
