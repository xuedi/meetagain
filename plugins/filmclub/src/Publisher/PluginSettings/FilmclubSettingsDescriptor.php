<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Form\FilmclubSettingsType;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Symfony\Component\Form\FormInterface;

final readonly class FilmclubSettingsDescriptor implements PluginSettingsDescriptorInterface
{
    public function __construct(
        private FilmclubSettingsService $settingsService,
    ) {}

    public function getKey(): string
    {
        return 'filmclub';
    }

    public function getTitleKey(): string
    {
        return 'filmclub_settings.page_title';
    }

    public function getFormType(): string
    {
        return FilmclubSettingsType::class;
    }

    public function getFormOptions(object $data): array
    {
        \assert($data instanceof FilmclubSettings);

        return [
            'tmdb_key_set' => $data->getEncryptedTmdbKey() !== null,
            'omdb_key_set' => $data->getEncryptedOmdbKey() !== null,
        ];
    }

    public function createDefault(): object
    {
        return new FilmclubSettings();
    }

    public function applyForm(object $data, FormInterface $form): void
    {
        \assert($data instanceof FilmclubSettings);

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
