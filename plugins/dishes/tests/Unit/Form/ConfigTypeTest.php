<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Form;

use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Dishes\ValueObject\Config;
use Plugin\Dishes\Form\ConfigType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

class ConfigTypeTest extends TestCase
{
    public function testBuildsOneFieldPerActiveLanguage(): void
    {
        // Arrange + Act
        $form = $this->factory(['en', 'de', 'zh'])->create(ConfigType::class, new Config());

        // Assert
        static::assertTrue($form->has('en'));
        static::assertTrue($form->has('de'));
        static::assertTrue($form->has('zh'));
        static::assertFalse($form->has('fr'));
    }

    public function testSubmitMapsValuesIntoFooterTextAndDropsEmpty(): void
    {
        // Arrange
        $form = $this->factory(['en', 'de', 'zh'])->create(ConfigType::class, new Config());

        // Act
        $form->submit(['en' => 'See you next time', 'de' => '', 'zh' => '下次见']);

        // Assert
        $config = $form->getData();
        static::assertInstanceOf(Config::class, $config);
        static::assertSame(['en' => 'See you next time', 'zh' => '下次见'], $config->getFooterText());
    }

    public function testHasPhoneticToggle(): void
    {
        // Arrange + Act
        $form = $this->factory(['en'])->create(ConfigType::class, new Config());

        // Assert
        static::assertTrue($form->has('phoneticInList'));
    }

    public function testSubmitEnablesPhonetic(): void
    {
        // Arrange
        $form = $this->factory(['en'])->create(ConfigType::class, new Config());

        // Act
        $form->submit(['phoneticInList' => '1', 'en' => '']);

        // Assert
        $config = $form->getData();
        static::assertInstanceOf(Config::class, $config);
        static::assertTrue($config->isPhoneticInList());
    }

    public function testUncheckedPhoneticIsFalse(): void
    {
        // Arrange
        $form = $this->factory(['en'])->create(ConfigType::class, (new Config())->setPhoneticInList(true));

        // Act - an unchecked checkbox is absent from the submitted payload
        $form->submit(['en' => '']);

        // Assert
        $config = $form->getData();
        static::assertInstanceOf(Config::class, $config);
        static::assertFalse($config->isPhoneticInList());
    }

    public function testExistingFooterPrefillsFields(): void
    {
        // Arrange
        $config = (new Config())->setFooterText(['en' => 'Prefilled']);

        // Act
        $form = $this->factory(['en', 'de'])->create(ConfigType::class, $config);

        // Assert
        static::assertSame('Prefilled', $form->get('en')->getData());
        static::assertNull($form->get('de')->getData());
    }

    /** @param list<string> $codes */
    private function factory(array $codes): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new ConfigType($languageService)], []))
            ->getFormFactory();
    }
}
