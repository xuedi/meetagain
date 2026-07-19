<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Repository\SettingsRepository;

readonly class SettingsService
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private EntityManagerInterface $em,
        private SecretBox $secretBox,
    ) {}

    public function getOrCreateGlobal(): Settings
    {
        return $this->settingsRepository->findGlobal() ?? new Settings();
    }

    public function save(Settings $settings): void
    {
        $this->em->persist($settings);
        $this->em->flush();
    }

    public function encryptKey(string $cleartext): string
    {
        return $this->secretBox->encrypt($cleartext);
    }

    public function getTmdbKey(Settings $settings): ?string
    {
        if ($settings->getEncryptedTmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedTmdbKey());
    }

    public function getOmdbKey(Settings $settings): ?string
    {
        if ($settings->getEncryptedOmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedOmdbKey());
    }
}
