<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\FilmclubGroupSettings;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;

readonly class FilmclubSettingsService
{
    public function __construct(
        private FilmclubGroupSettingsRepository $settingsRepository,
        private EntityManagerInterface $em,
        private SecretBox $secretBox,
    ) {}

    public function getOrCreate(int $groupId): FilmclubGroupSettings
    {
        $settings = $this->settingsRepository->findByGroupId($groupId);
        if ($settings === null) {
            $settings = new FilmclubGroupSettings();
            $settings->setGroupId($groupId);
        }

        return $settings;
    }

    public function save(FilmclubGroupSettings $settings): void
    {
        $this->em->persist($settings);
        $this->em->flush();
    }

    public function encryptKey(string $cleartext): string
    {
        return $this->secretBox->encrypt($cleartext);
    }

    public function getTmdbKey(FilmclubGroupSettings $settings): ?string
    {
        if ($settings->getEncryptedTmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedTmdbKey());
    }

    public function getOmdbKey(FilmclubGroupSettings $settings): ?string
    {
        if ($settings->getEncryptedOmdbKey() === null) {
            return null;
        }

        return $this->secretBox->decrypt($settings->getEncryptedOmdbKey());
    }
}
