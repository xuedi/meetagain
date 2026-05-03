<?php declare(strict_types=1);

namespace Plugin\Glossary\Service\Api;

use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;

readonly class GlossarySerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Glossary $entry): array
    {
        return [
            'id' => $entry->getId(),
            'phrase' => $entry->getPhrase(),
            'pinyin' => $entry->getPinyin(),
            'explanation' => $entry->getExplanation(),
            'categorySlug' => $this->categorySlug($entry->getCategory()),
            'createdAt' => $entry->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function categorySlug(?Category $category): ?string
    {
        return match ($category) {
            Category::Greeting => 'greeting',
            Category::Swearing => 'swearing',
            Category::Flirting => 'flirting',
            Category::Slang => 'slang',
            Category::Abbreviation => 'abbreviation',
            Category::Regular => 'regular',
            Category::Idioms => 'idioms',
            null => null,
        };
    }

    public function categoryFromSlug(string $slug): ?Category
    {
        return match ($slug) {
            'greeting' => Category::Greeting,
            'swearing' => Category::Swearing,
            'flirting' => Category::Flirting,
            'slang' => Category::Slang,
            'abbreviation' => Category::Abbreviation,
            'regular' => Category::Regular,
            'idioms' => Category::Idioms,
            default => null,
        };
    }
}
