<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Stopwatch\Stopwatch;

readonly class TranslationImportService
{
    public function __construct(
        private TranslationRepository $translationRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $entityManager,
        private TranslationFileManager $fileManager,
        private CommandService $commandService,
        private ConfigService $configService,
        private TranslationService $translationService,
    ) {
    }

    public function extract(): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('executeCommand');

        $numberTranslationCount = 0;
        $newTranslations = 0;
        $this->fileManager->cleanUpTranslationFiles();
        $output = $this->commandService->extractTranslations();
        $deletedTranslations = 0;

        $dataBase = $this->translationRepo->getUniqueList();
        $importUser = $this->userRepo->findOneBy(['id' => $this->configService->getSystemUserId()]);

        if ($importUser === null) {
            throw new RuntimeException('System user not found for translation import');
        }

        foreach ($this->fileManager->getTranslationFiles() as $file) {
            $parts = explode('.', (string) $file->getFilename());
            if (count($parts) !== 3) {
                continue;
            }
            [$name, $lang, $ext] = $parts;
            $translations = $this->removeDuplicates(include $file->getPathname());
            foreach (array_keys($translations) as $placeholder) {
                if (!isset($dataBase[$lang]) || !in_array($placeholder, $dataBase[$lang], true)) {
                    $translation = new Translation();
                    $translation->setLanguage($lang);
                    $translation->setPlaceholder($placeholder);
                    $translation->setCreatedAt(new DateTimeImmutable());
                    $translation->setUser($importUser);
                    $this->entityManager->persist($translation);
                    ++$newTranslations;
                }
                ++$numberTranslationCount;
            }
        }
        $this->entityManager->flush();
        $this->translationService->publish();

        return [
            'count' => $numberTranslationCount,
            'new' => $newTranslations,
            'deleted' => $deletedTranslations,
            'extractionTime' => (string) $stopwatch->stop('executeCommand'),
            'output' => $output,
        ];
    }

    public function importForLocalDevelopment(string $apiUrl): void
    {
        $json = file_get_contents($apiUrl);
        if ($json === false) {
            throw new RuntimeException('Could not fetch translations from ' . $apiUrl);
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Clear suggestions first due to FK to translation, then clear translations
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM translation_suggestion');
        $conn->executeStatement('DELETE FROM translation');

        $systemUserId = $this->configService->getSystemUserId();
        $user = $this->userRepo->findOneBy(['id' => $systemUserId]);

        $batchSize = 100;
        $i = 1;
        foreach ($data as $item) {
            $translation = new Translation();
            $translation->setUser($user);
            $translation->setLanguage($item['language']);
            $translation->setPlaceholder($item['placeholder']);
            $translation->setTranslation($item['translation']);
            $translation->setCreatedAt(new DateTimeImmutable());

            $this->entityManager->persist($translation);

            if (($i % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $user = $this->userRepo->findOneBy(['id' => $systemUserId]);
            }
            ++$i;
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->translationService->publish();
    }

    private function removeDuplicates(array $translations): array
    {
        $cleanedList = [];
        foreach ($translations as $key => $translation) {
            if (!isset($cleanedList[strtolower((string) $key)])) {
                $cleanedList[strtolower((string) $key)] = $translation;
            }
        }

        return $cleanedList;
    }
}
