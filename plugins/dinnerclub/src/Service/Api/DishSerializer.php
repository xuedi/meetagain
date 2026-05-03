<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service\Api;

use Plugin\Dinnerclub\Entity\Dish;

readonly class DishSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toSummary(Dish $dish, string $locale, string $baseUrl): array
    {
        return [
            'id' => $dish->getId(),
            'name' => $this->resolveName($dish, $locale),
            'phonetic' => $dish->getPhonetic(),
            'origin' => $dish->getOrigin(),
            'previewImageUrl' => $this->buildImageUrl($dish, $baseUrl),
            'likes' => $dish->getLikes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetail(Dish $dish, string $locale, string $baseUrl): array
    {
        $translations = [];
        foreach ($dish->getTranslation() as $t) {
            $translations[$t->getLanguage() ?? ''] = [
                'name' => $t->getName(),
                'description' => $t->getDescription(),
            ];
        }

        return [
            ...$this->toSummary($dish, $locale, $baseUrl),
            'description' => $this->resolveDescription($dish, $locale),
            'translations' => $translations,
        ];
    }

    private function resolveName(Dish $dish, string $locale): string
    {
        $name = $dish->getTranslatedName($locale);

        return $name !== '' ? $name : $dish->getAnyTranslatedName();
    }

    private function resolveDescription(Dish $dish, string $locale): string
    {
        $description = $dish->getTranslatedDescription($locale);

        return $description !== '' ? $description : $dish->getAnyTranslatedDescription();
    }

    private function buildImageUrl(Dish $dish, string $baseUrl): ?string
    {
        $image = $dish->getPreviewImage();
        if ($image === null || $image->getHash() === null) {
            return null;
        }

        return sprintf('%s/images/thumbnails/%s_600x400.webp', rtrim($baseUrl, '/'), $image->getHash());
    }
}
