<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Stopwatch\Stopwatch;

readonly class TranslationService
{
    public function __construct(
        private TranslationRepository $translationRepo,
        private EntityManagerInterface $entityManager,
        private TranslationFileManager $fileManager,
        private LanguageService $languageService,
        private CommandService $commandService,
    ) {
    }

    /** TODO: move to repo */
    public function getMatrix(): array
    {
        return $this->translationRepo->getMatrix();
    }

    /** TODO: move to repo */
    public function saveMatrix(Request $request): void
    {
        $dataBase = $this->translationRepo->buildKeyValueList();
        $params = $request->getPayload();

        foreach ($params as $key => $newTranslation) {
            if (array_key_exists($key, $dataBase) && $dataBase[$key] !== $newTranslation) {
                $translationEntity = $this->translationRepo->findOneBy(['id' => $key]);
                if ($translationEntity === null || empty($newTranslation)) {
                    continue;
                }

                $translationEntity->setTranslation($newTranslation);
                $translationEntity->setCreatedAt(new DateTimeImmutable());
                $this->entityManager->persist($translationEntity);
            }
        }

        $this->entityManager->flush();
    }

    public function publish(): array
    {
        $published = 0;
        $cleanedUp = $this->fileManager->cleanUpTranslationFiles();
        $locales = $this->languageService->getEnabledCodes();

        foreach ($locales as $locale) {
            $translations = [];
            $entities = $this->translationRepo->findBy(['language' => $locale]);
            foreach ($entities as $translation) {
                $translations[strtolower($translation->getPlaceholder())] = $translation->getTranslation() ?? '';
                $published++;
            }
            $this->fileManager->writeTranslationFile($locale, $translations);
        }
        $this->commandService->clearCache();

        return [
            'cleanedUp' => $cleanedUp,
            'published' => $published,
            'languages' => $locales,
        ];
    }

    public function getLanguageCodes(): array
    {
        return $this->languageService->getEnabledCodes();
    }

    public function isValidLanguageCodes(string $code): bool
    {
        return $this->languageService->isValidCode($code);
    }

    public function getAltLangList(string $currentLocale, string $currentUri): array
    {
        $altLangList = array_fill_keys($this->getLanguageCodes(), $currentUri);
        unset($altLangList[$currentLocale]); // remove current
        foreach ($altLangList as $languageCode => $link) {
            $altLangList[$languageCode] = $this->replaceUriLanguageCode($link, $languageCode);
        }

        return $altLangList;
    }

    public function replaceUriLanguageCode(string $link, string $newCode): string
    {
        $languages = $this->getLanguageCodes();
        $trimmedLink = trim($link, '/');

        // Just language
        if (in_array($trimmedLink, $languages, true)) {
            return sprintf('/%s/', $newCode);
        }

        // whatever URI
        $chunks = explode('/', $trimmedLink);
        if (in_array($chunks[0], $languages, true)) {
            $chunks[0] = $newCode;
            return sprintf('/%s', implode('/', $chunks));
        }

        return $link;
    }
}
