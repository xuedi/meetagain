<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Films\Entity\FilmsSettings;
use Plugin\Films\Repository\FilmsSettingsRepository;

readonly class FilmsSettingsService
{
    public function __construct(
        private FilmsSettingsRepository $settingsRepository,
        private EntityManagerInterface $em,
        private SecretBox $secretBox,
    ) {}

    public function getOrCreateGlobal(): FilmsSettings
    {
        return $this->settingsRepository->findGlobal() ?? new FilmsSettings();
    }

    public function save(FilmsSettings $settings): void
    {
        $this->em->persist($settings);
        $this->em->flush();
    }

    public function encryptKey(string $cleartext): string
    {
        return $this->secretBox->encrypt($cleartext);
    }

    public function getTmdbKey(FilmsSettings $settings): ?string
    {
        if ($settings->getEncryptedTmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedTmdbKey());
    }

    public function getOmdbKey(FilmsSettings $settings): ?string
    {
        if ($settings->getEncryptedOmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedOmdbKey());
    }
}
