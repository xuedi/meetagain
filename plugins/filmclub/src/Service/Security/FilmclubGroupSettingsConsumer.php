<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service\Security;

use App\Service\Security\SecretBoxConsumerInterface;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;

readonly class FilmclubGroupSettingsConsumer implements SecretBoxConsumerInterface
{
    public function __construct(
        private FilmclubGroupSettingsRepository $settingsRepository,
    ) {}

    public function getKey(): string
    {
        return 'filmclub_admin_secretbox.consumer_settings';
    }

    public function count(): int
    {
        return $this->settingsRepository->countWithEncryptedCredentials();
    }
}
