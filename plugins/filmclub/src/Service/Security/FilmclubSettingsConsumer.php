<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service\Security;

use App\Service\Security\SecretBoxConsumerInterface;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;

readonly class FilmclubSettingsConsumer implements SecretBoxConsumerInterface
{
    public function __construct(
        private FilmclubSettingsRepository $settingsRepository,
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
