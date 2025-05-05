<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;

readonly class TranslationService
{
    public function __construct(
        private TranslationRepository $translationRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $entityManager,
        private Filesystem $fs,
        private ParameterBagInterface $appParams,
        private JustService $just,
        private string $kernelProjectDir,
    ) {
    }

    /** TODO: move to repo */
    public function getMatrix(): array
    {
        $structuredList = [];
        $translations = $this->translationRepo->findAll();
        foreach ($translations as $translation) {
            $id = $translation->getId();
            $lang = $translation->getLanguage();
            $placeholder = $translation->getPlaceholder();
            $translation = $translation->getTranslation() ?? '';
            $structuredList[$placeholder][$lang] = [
                'id' => $id,
                'value' => $translation,
            ];
        }
        ksort($structuredList, SORT_NATURAL);

        return $structuredList;
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

    public function extract(): array
    {
        $numberTranslationCount = 0;
        $newTranslations = 0;
        $this->cleanUpTranslationFiles();
        $extractionTime = $this->just->command('translationsExtract');
        $deletedTranslations = 0; //$this->deleteOrphanedTranslations();

        $path = $this->kernelProjectDir . '/translations/';
        $dataBase = $this->translationRepo->getUniqueList();
        $importUser = $this->userRepo->findOneBy(['email' => 'system@beijingcode.org']);

        $finder = new Finder();
        $finder->files()->in($path)->depth(0)->name(['messages*.php']);
        foreach ($finder as $file) {
            [$name, $lang, $ext] = explode('.', $file->getFilename());
            $translations = $this->removeDuplicates(include($file->getPathname()));
            foreach (array_keys($translations) as $placeholder) {
                if (!in_array($placeholder, $dataBase[$lang], true)) {
                    $translation = new Translation();
                    $translation->setLanguage($lang);
                    $translation->setPlaceholder($placeholder);
                    $translation->setCreatedAt(new DateTimeImmutable());
                    $translation->setUser($importUser);
                    $this->entityManager->persist($translation);
                    $newTranslations++;
                }
                $numberTranslationCount++;
            }
        }
        $this->entityManager->flush();
        $this->publish();

        return [
            'count' => $numberTranslationCount,
            'new' => $newTranslations,
            'deleted' => $deletedTranslations,
            'extractionTime' => $extractionTime,
        ];
    }

    public function publish(): array
    {
        $published = 0;
        $cleanedUp = $this->cleanUpTranslationFiles();
        $path = $this->kernelProjectDir . '/translations/';
        $locales = $this->appParams->get('kernel.enabled_locales');

        // create new translation files
        foreach ($locales as $locale) {
            $file = $path . 'messages.' . $locale . '.php';
            $this->fs->appendToFile($file, '<?php return array (');
            $translations = $this->translationRepo->findBy(['language' => $locale]);
            foreach ($translations as $translation) {
                $this->fs->appendToFile($file, sprintf(
                    "'%s' => '%s',",
                    $translation->getPlaceholder(),
                    $translation->getTranslation(),
                ));
                $published++;
            }
            $this->fs->appendToFile($file, ');');
        }
        $this->just->command('clearCache');

        return [
            'cleanedUp' => $cleanedUp,
            'published' => $published,
            'languages' => $locales,
        ];
    }

    public function getLanguageCodes(): array
    {
        return $this->appParams->get('kernel.enabled_locales');
    }

    public function isValidLanguageCodes(string $code): bool
    {
        return in_array($code, $this->appParams->get('kernel.enabled_locales'));
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
        if(in_array($trimmedLink, $languages)) {
            return sprintf('/%s/', $newCode);
        }

        // whatever URI
        $chunks = explode('/', $trimmedLink);
        if(in_array($chunks[0], $languages)) {
            $chunks[0] = $newCode;
            return sprintf('/%s', implode('/', $chunks));
        }

        return $link;
    }

    public function importForLocalDevelopment(string $apiUrl): void
    {
        $json = file_get_contents($apiUrl);
        $data = json_decode($json, true);

        $this->entityManager->getConnection()->executeQuery(" TRUNCATE TABLE translation");
        $user = $this->userRepo->findOneBy(['email' => 'system@beijingcode.org']);

        foreach ($data as $item) {
            $translation = new Translation();
            $translation->setUser($user);
            $translation->setLanguage($item['language']);
            $translation->setPlaceholder($item['placeholder']);
            $translation->setTranslation($item['translation']);
            $translation->setCreatedAt(new DateTimeImmutable());

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        $this->publish();
    }

    private function removeDuplicates(array $translations): array
    {
        $cleanedList = [];
        foreach ($translations as $key => $translation) {
            if(!isset($cleanedList[strtolower($key)])) {
                $cleanedList[strtolower($key)] = $translation;
            }
        }
        return $cleanedList;
    }

    private function cleanUpTranslationFiles(): int
    {
        $cleanedUp = 0;
        $path = $this->kernelProjectDir . '/translations/';
        $finder = new Finder();
        $finder->files()->in($path)->depth(0)->name(['*.php']);
        foreach ($finder as $file) {
            $this->fs->remove($file->getPathname());
            $cleanedUp++;
        }

        return $cleanedUp;
    }
}
