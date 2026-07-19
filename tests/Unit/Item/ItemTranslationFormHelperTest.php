<?php declare(strict_types=1);

namespace App\Tests\Unit\Item;

use App\Item\ItemTranslationFormHelper;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

class ItemTranslationFormHelperTest extends TestCase
{
    public function testAddsOneUnmappedFieldPerLanguageSeededFromLoader(): void
    {
        // Arrange
        $helper = $this->helper(['en', 'de', 'zh']);
        $builder = Forms::createFormFactory()->createBuilder();
        $values = ['en' => 'Rice', 'de' => 'Reis', 'zh' => '米饭'];

        // Act
        $helper->addTranslatedFields($builder, [
            'name' => [TextType::class, ['required' => false]],
        ], static fn(string $code, string $field): string => $values[$code] ?? '');
        $form = $builder->getForm();

        // Assert
        static::assertTrue($form->has('name-en'));
        static::assertTrue($form->has('name-de'));
        static::assertTrue($form->has('name-zh'));
        static::assertFalse($form->has('name-fr'));
        static::assertSame('Reis', $form->get('name-de')->getData());
        static::assertFalse($form->get('name-en')->getConfig()->getMapped());
    }

    public function testExtractTranslationsMapsSubmittedValuesPerLanguage(): void
    {
        // Arrange
        $helper = $this->helper(['en', 'de']);
        $builder = Forms::createFormFactory()->createBuilder();
        $helper->addTranslatedFields($builder, [
            'name' => [TextType::class, ['required' => false]],
            'recipe' => [TextareaType::class, ['required' => false]],
        ], static fn(): string => '');
        $form = $builder->getForm();

        // Act
        $form->submit([
            'name-en' => 'Rice', 'recipe-en' => 'Boil',
            'name-de' => 'Reis', 'recipe-de' => 'Kochen',
        ]);

        // Assert
        static::assertSame([
            'en' => ['name' => 'Rice', 'recipe' => 'Boil'],
            'de' => ['name' => 'Reis', 'recipe' => 'Kochen'],
        ], $helper->extractTranslations($form, ['name', 'recipe']));
    }

    /** @param list<string> $codes */
    private function helper(array $codes): ItemTranslationFormHelper
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);

        return new ItemTranslationFormHelper($languageService);
    }
}
