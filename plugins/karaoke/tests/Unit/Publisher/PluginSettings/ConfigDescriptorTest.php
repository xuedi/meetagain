<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Publisher\PluginSettings;

use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Form\ConfigType;
use Plugin\Karaoke\Publisher\PluginSettings\ConfigDescriptor;
use Plugin\Karaoke\ValueObject\Config;
use Psr\Log\NullLogger;
use SensitiveParameter;
use Symfony\Component\Form\FormInterface;

class ConfigDescriptorTest extends TestCase
{
    public function testTheSectionIsScopableAndUsesTheKaraokeForm(): void
    {
        // Arrange
        $descriptor = $this->descriptor();

        // Act + Assert
        static::assertSame('karaoke', $descriptor->getKey());
        static::assertSame('karaoke', $descriptor->getPluginKey());
        static::assertTrue($descriptor->isScopable());
        static::assertSame(ConfigType::class, $descriptor->getFormType());
    }

    public function testFormOptionsReportWhetherAKeyIsStored(): void
    {
        // Arrange
        $descriptor = $this->descriptor();

        // Act
        $withoutKey = $descriptor->getFormOptions(new Config());
        $withKey = $descriptor->getFormOptions(new Config()->setEncryptedYoutubeApiKey('cipher'));

        // Assert
        static::assertSame(['youtube_api_key_set' => false], $withoutKey);
        static::assertSame(['youtube_api_key_set' => true], $withKey);
    }

    public function testSubmittingAKeyStoresItEncrypted(): void
    {
        // Arrange
        $config = new Config();

        // Act
        $this->descriptor()->applyForm($config, $this->form('plain-key', false));

        // Assert
        static::assertSame('enc:plain-key', $config->getEncryptedYoutubeApiKey());
    }

    public function testAnEmptySubmissionKeepsTheStoredKey(): void
    {
        // Arrange
        $config = new Config()->setEncryptedYoutubeApiKey('enc:old');

        // Act
        $this->descriptor()->applyForm($config, $this->form('', false));

        // Assert
        static::assertSame('enc:old', $config->getEncryptedYoutubeApiKey());
    }

    public function testTickingClearRemovesTheStoredKey(): void
    {
        // Arrange
        $config = new Config()->setEncryptedYoutubeApiKey('enc:old');

        // Act
        $this->descriptor()->applyForm($config, $this->form('new-key', true));

        // Assert
        static::assertNull($config->getEncryptedYoutubeApiKey());
    }

    private function descriptor(): ConfigDescriptor
    {
        $secretBox = $this->createStub(SecretBox::class);
        $secretBox->method('encrypt')->willReturnCallback(static fn(string $plain): string => 'enc:' . $plain);
        $scopeProvider = $this->createStub(ScopeProviderInterface::class);
        $scopeProvider->method('getScopeId')->willReturn(null);

        return new ConfigDescriptor($secretBox, new NullLogger(), [$scopeProvider]);
    }

    private function form(#[SensitiveParameter] string $key, bool $clear): FormInterface
    {
        $keyField = $this->createStub(FormInterface::class);
        $keyField->method('getData')->willReturn($key);

        $clearField = $this->createStub(FormInterface::class);
        $clearField->method('getData')->willReturn($clear);

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnCallback(static fn(string $name): FormInterface => str_starts_with($name, 'clear') ? $clearField : $keyField);

        return $form;
    }
}
