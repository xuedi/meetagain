<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\Image;
use App\Localization\LocalizedContentRow;
use App\Localization\LocalizedContentSourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class ImageAltSource implements LocalizedContentSourceInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'image_alt';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_image_alt';
    }

    #[Override]
    public function getOwnerType(): string
    {
        return self::OWNER_IMAGE;
    }

    #[Override]
    public function countOutsideLocales(array $ownerIds, array $keepLocales): int
    {
        return count($this->findOutsideLocales($ownerIds, $keepLocales));
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        foreach ($this->fetchImages($ownerIds) as $image) {
            foreach ($this->staleLocales($image, $keepLocales) as $locale) {
                $rows[] = new LocalizedContentRow(
                    sourceKey: $this->getKey(),
                    ownerId: (int) $image->getId(),
                    locale: $locale,
                    ownerLabel: (string) $image->getHash(),
                    preview: (string) $image->getAltTranslation($locale),
                );
            }
        }

        return $rows;
    }

    #[Override]
    public function deleteOutsideLocales(array $ownerIds, array $keepLocales): int
    {
        $cleared = 0;
        foreach ($this->fetchImages($ownerIds) as $image) {
            foreach ($this->staleLocales($image, $keepLocales) as $locale) {
                $image->setAltTranslation($locale, null);
                $cleared++;
            }
        }

        if ($cleared > 0) {
            $this->em->flush();
        }

        return $cleared;
    }

    /**
     * @param list<int> $ownerIds
     * @return list<Image>
     */
    private function fetchImages(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        return $this->em
            ->createQueryBuilder()
            ->select('image')
            ->from(Image::class, 'image')
            ->where('image.id IN (:ownerIds)')
            ->andWhere('image.altTranslations IS NOT NULL')
            ->setParameter('ownerIds', $ownerIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $keepLocales
     * @return list<string>
     */
    private function staleLocales(Image $image, array $keepLocales): array
    {
        if ($keepLocales === []) {
            return [];
        }

        $stale = [];
        foreach (array_keys($image->getAltTranslations()) as $locale) {
            if (in_array($locale, $keepLocales, true)) {
                continue;
            }

            $stale[] = $locale;
        }

        return $stale;
    }
}
