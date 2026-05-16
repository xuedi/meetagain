<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsProviderInterface;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Form\FilmclubSettingsType;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Symfony\Component\Form\FormInterface;

final class FilmclubSettingsProvider implements PluginSettingsProviderInterface
{
    private ?FilmclubSettings $cached = null;

    public function __construct(
        private readonly FilmclubSettingsService $settingsService,
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

    public function loadData(): object
    {
        return $this->cached ??= $this->settingsService->getOrCreateGlobal();
    }

    public function getFormOptions(): array
    {
        $data = $this->loadData();

        return [
            'tmdb_key_set' => $data->getEncryptedTmdbKey() !== null,
            'omdb_key_set' => $data->getEncryptedOmdbKey() !== null,
        ];
    }

    public function save(object $data, FormInterface $form): void
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

        $this->settingsService->save($data);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
