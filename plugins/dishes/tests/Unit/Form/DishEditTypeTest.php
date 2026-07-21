<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Form;

use App\Item\ItemTranslationFormHelper;
use App\Item\Taxonomy\ItemAssignmentFormHelper;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Form\DishEditType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class DishEditTypeTest extends TestCase
{
    public function testBuildsTranslatableFieldsForEachLanguageSeededFromDish(): void
    {
        // Arrange
        $dish = (new Dish())->setPhonetic('mǐ fàn')->setOrigin('China');
        $german = new DishTranslation();
        $german->setLanguage('de');
        $german->setName('Reis');
        $german->setDescription('Beilage');
        $dish->addTranslation($german);

        // Act
        $form = $this->factory(['en', 'de', 'zh'])->create(DishEditType::class, null, ['dish' => $dish]);

        // Assert - one field group per language, plus the language-neutral fields
        static::assertTrue($form->has('name-en'));
        static::assertTrue($form->has('name-de'));
        static::assertTrue($form->has('description-zh'));
        static::assertTrue($form->has('recipe-zh'));
        static::assertTrue($form->has('phonetic'));
        static::assertTrue($form->has('origin'));

        // Assert - values seeded from the dish, not from the UI locale
        static::assertSame('Reis', $form->get('name-de')->getData());
        static::assertSame('', $form->get('name-en')->getData());
        static::assertSame('mǐ fàn', $form->get('phonetic')->getData());
        static::assertSame('China', $form->get('origin')->getData());
    }

    /** @param list<string> $codes */
    private function factory(array $codes): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $helper = new ItemTranslationFormHelper($languageService);
        $assignmentHelper = $this->createStub(ItemAssignmentFormHelper::class);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([new DishEditType($translator, $helper, $assignmentHelper)], []))
            ->getFormFactory();
    }
}
