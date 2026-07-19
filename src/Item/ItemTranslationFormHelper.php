<?php declare(strict_types=1);

namespace App\Item;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Builds and reads the per-language field set behind the shared item translation toggle.
 *
 * Each translatable field is added once per enabled admin language as an unmapped
 * "{field}-{code}" child (e.g. name-en, name-de), so every language is editable at once
 * regardless of the UI locale. The language set comes from LanguageService, never the
 * navbar switcher. Pair with templates/_components/item/translation_fields.html.twig.
 */
readonly class ItemTranslationFormHelper
{
    public function __construct(
        private LanguageService $languageService,
    ) {}

    /**
     * @param array<string, array{0: class-string<FormTypeInterface>, 1: array<string, mixed>}> $fields field stem => [type, options]
     * @param callable(string $code, string $field): mixed                   $valueLoader seeds each child's initial value
     */
    public function addTranslatedFields(FormBuilderInterface $builder, array $fields, callable $valueLoader): void
    {
        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            foreach ($fields as $field => [$type, $options]) {
                $builder->add("{$field}-{$code}", $type, [
                    ...$options,
                    'mapped' => false,
                    'data' => $valueLoader($code, $field),
                ]);
            }
        }
    }

    /**
     * @param list<string> $fieldNames
     * @return array<string, array<string, mixed>> language code => [field => submitted value]
     */
    public function extractTranslations(FormInterface $form, array $fieldNames): array
    {
        $translations = [];
        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            foreach ($fieldNames as $field) {
                $translations[$code][$field] = $form->get("{$field}-{$code}")->getData();
            }
        }

        return $translations;
    }
}
