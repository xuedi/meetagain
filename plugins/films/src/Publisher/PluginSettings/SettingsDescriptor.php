<?php declare(strict_types=1);

namespace Plugin\Films\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Form\SettingsType;
use Plugin\Films\Service\SettingsService;
use Symfony\Component\Form\FormInterface;

final readonly class SettingsDescriptor implements PluginSettingsDescriptorInterface
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function getKey(): string
    {
        return 'films';
    }

    public function getTitleKey(): string
    {
        return 'films_settings.page_title';
    }

    public function getFormType(): string
    {
        return SettingsType::class;
    }

    public function getFormOptions(object $data): array
    {
        \assert($data instanceof Settings);

        return [
            'tmdb_key_set' => $data->getEncryptedTmdbKey() !== null,
            'omdb_key_set' => $data->getEncryptedOmdbKey() !== null,
        ];
    }

    public function createDefault(): object
    {
        return new Settings();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof Settings);

        $tmdbKey = $form->get('tmdbKey')->getData();
        $clearTmdb = (bool) $form->get('clearTmdbKey')->getData();
        $omdbKey = $form->get('omdbKey')->getData();
        $clearOmdb = (bool) $form->get('clearOmdbKey')->getData();

        if ($clearTmdb) {
            $data->setEncryptedTmdbKey(null);
        } elseif ($tmdbKey !== null && $tmdbKey !== '') {
            $data->setEncryptedTmdbKey($this->settingsService->encryptKey($tmdbKey));
        }

        if ($clearOmdb) {
            $data->setEncryptedOmdbKey(null);
        } elseif ($omdbKey !== null && $omdbKey !== '') {
            $data->setEncryptedOmdbKey($this->settingsService->encryptKey($omdbKey));
        }
    }

    public function getPriority(): int
    {
        return 0;
    }
}
