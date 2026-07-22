<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

readonly class ImageAltService
{
    public function __construct(
        private ImageRepository $imageRepository,
        private LanguageService $languageService,
        private AltLocaleRequirementResolver $altLocaleRequirementResolver,
        private EntityManagerInterface $entityManager,
        private ImageAltStatusCache $imageAltStatusCache,
    ) {}

    public function getSourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }

    /**
     * @return array{items: list<array{image: Image, requiredLocales: list<string>, missingLocales: list<string>}>, nextAfterId: ?int}
     */
    public function findMissingAltPage(?int $afterId, int $limit): array
    {
        $candidates = $this->imageRepository->findAuditCandidates($afterId, $limit);
        if ($candidates === []) {
            return ['items' => [], 'nextAfterId' => null];
        }

        $sourceLocale = $this->getSourceLocale();
        $requiredByImageId = $this->altLocaleRequirementResolver->getRequiredAltLocalesForImages($candidates);

        $items = [];
        foreach ($candidates as $image) {
            $required = $requiredByImageId[(int) $image->getId()] ?? [];
            $missing = $image->missingAltLocales($required, $sourceLocale);
            if ($missing === []) {
                continue;
            }
            $items[] = ['image' => $image, 'requiredLocales' => $required, 'missingLocales' => $missing];
        }

        $nextAfterId = count($candidates) === $limit ? $candidates[array_key_last($candidates)]->getId() : null;

        return ['items' => $items, 'nextAfterId' => $nextAfterId];
    }

    /**
     * @param array<string, string> $localeToText
     * @throws InvalidArgumentException when a locale is not in the image's required set
     */
    public function applyAlt(Image $image, array $localeToText): void
    {
        $required = $this->altLocaleRequirementResolver->getRequiredAltLocales($image);
        foreach (array_keys($localeToText) as $locale) {
            if (!in_array($locale, $required, true)) {
                throw new InvalidArgumentException(sprintf('Locale "%s" is not required for image #%d', $locale, (int) $image->getId()));
            }
        }

        $sourceLocale = $this->getSourceLocale();
        foreach ($localeToText as $locale => $text) {
            $text = mb_substr(trim($text), 0, 255);
            if ($locale === $sourceLocale) {
                $image->setAlt($text === '' ? null : $text);
            } else {
                $image->setAltTranslation($locale, $text === '' ? null : $text);
            }
        }

        $image->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();
        $this->imageAltStatusCache->invalidateImage((int) $image->getId());
    }
}
