<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;

readonly class FilmclubSettingsService
{
    public function __construct(
        private FilmclubSettingsRepository $settingsRepository,
        private EntityManagerInterface $em,
        private SecretBox $secretBox,
    ) {}

    public function getOrCreateGlobal(): FilmclubSettings
    {
        return $this->settingsRepository->findGlobal() ?? new FilmclubSettings();
    }

    public function save(FilmclubSettings $settings): void
    {
        $this->em->persist($settings);
        $this->em->flush();
    }

    public function encryptKey(string $cleartext): string
    {
        return $this->secretBox->encrypt($cleartext);
    }

    public function getTmdbKey(FilmclubSettings $settings): ?string
    {
        if ($settings->getEncryptedTmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedTmdbKey());
    }

    public function getOmdbKey(FilmclubSettings $settings): ?string
    {
        if ($settings->getEncryptedOmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedOmdbKey());
    }
}
