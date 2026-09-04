<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Publisher\PluginSettings;

use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Publisher\PluginSettings\StoreInterface;
use App\Service\Admin\PluginSettingsService;
use App\Service\Security\SecretBox;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Form\ConfigType;
use Plugin\Boardgames\Publisher\PluginSettings\ConfigDescriptor;
use Plugin\Boardgames\ValueObject\Config;
use Psr\Log\NullLogger;
use Symfony\Component\Form\FormInterface;

class ConfigDescriptorTest extends TestCase
{
    public function testDescriptorContract(): void
    {
        // Arrange
        $descriptor = $this->descriptor();

        // Act + Assert
        static::assertSame('boardgames', $descriptor->getKey());
        static::assertSame('boardgames', $descriptor->getPluginKey());
        static::assertSame('boardgames_config.page_title', $descriptor->getTitleKey());
        static::assertSame(ConfigType::class, $descriptor->getFormType());
        static::assertSame(0, $descriptor->getPriority());
    }

    public function testTheSectionIsScopableSoAScopeMayHoldItsOwnToken(): void
    {
        // Arrange
        $descriptor = $this->descriptor();

        // Act + Assert
        static::assertTrue($descriptor->isScopable());
    }

    public function testFormOptionsReportWhetherATokenIsStored(): void
    {
        // Arrange
        $descriptor = $this->descriptor();

        // Act
        $withoutToken = $descriptor->getFormOptions(new Config());
        $withToken = $descriptor->getFormOptions(new Config()->setEncryptedBggToken('cipher'));

        // Assert
        static::assertSame(['bgg_token_set' => false], $withoutToken);
        static::assertSame(['bgg_token_set' => true], $withToken);
    }

    public function testSubmittingATokenStoresItEncrypted(): void
    {
        // Arrange
        $descriptor = $this->descriptor();
        $config = new Config();

        // Act
        $descriptor->applyForm($config, $this->form('plain-token', false));

        // Assert
        static::assertSame('enc:plain-token', $config->getEncryptedBggToken());
    }

    public function testAnEmptySubmissionKeepsTheStoredToken(): void
    {
        // Arrange
        $descriptor = $this->descriptor();
        $config = new Config()->setEncryptedBggToken('enc:old');

        // Act
        $descriptor->applyForm($config, $this->form('', false));

        // Assert
        static::assertSame('enc:old', $config->getEncryptedBggToken());
    }

    public function testTickingClearRemovesTheStoredToken(): void
    {
        // Arrange
        $descriptor = $this->descriptor();
        $config = new Config()->setEncryptedBggToken('enc:old');

        // Act
        $descriptor->applyForm($config, $this->form('new-token', true));

        // Assert
        static::assertNull($config->getEncryptedBggToken());
    }

    public function testTrustIsSwitchedOffWhenCirculationIsOff(): void
    {
        // Arrange
        $descriptor = $this->descriptor();
        $config = new Config()->setTrustSystem(true);

        // Act
        $descriptor->applyForm($config, $this->form('', false));

        // Assert
        static::assertFalse($config->isTrustSystem());
    }

    public function testAScopeOverrideBeatsTheGlobalToken(): void
    {
        // Arrange
        $resolver = $this->resolver('scope-7', new Config()->setEncryptedBggToken('enc:global'), new Config()->setEncryptedBggToken('enc:scoped'));

        // Act
        $config = $resolver->resolve('boardgames');

        // Assert
        static::assertInstanceOf(Config::class, $config);
        static::assertSame('enc:scoped', $config->getEncryptedBggToken());
    }

    public function testAScopeWithoutARecordFallsBackToTheGlobalToken(): void
    {
        // Arrange
        $resolver = $this->resolver('scope-7', new Config()->setEncryptedBggToken('enc:global'), null);

        // Act
        $config = $resolver->resolve('boardgames');

        // Assert
        static::assertInstanceOf(Config::class, $config);
        static::assertSame('enc:global', $config->getEncryptedBggToken());
    }

    public function testWithNoRecordAnywhereTheNeutralDefaultIsReturned(): void
    {
        // Arrange
        $resolver = $this->resolver(null, null, null);

        // Act
        $config = $resolver->resolve('boardgames');

        // Assert
        static::assertInstanceOf(Config::class, $config);
        static::assertNull($config->getEncryptedBggToken());
    }

    private function descriptor(?string $scopeId = null): ConfigDescriptor
    {
        return new ConfigDescriptor($this->secretBox(), new NullLogger(), [$this->scopeProvider($scopeId)]);
    }

    private function resolver(?string $scopeId, ?Config $global, ?Config $scoped): Resolver
    {
        $descriptors = new PluginSettingsService([$this->descriptor($scopeId)]);

        return new Resolver(
            $descriptors,
            [$this->store(null, $global), $this->store($scopeId, $scoped)],
            [$this->scopeProvider($scopeId)],
        );
    }

    private function scopeProvider(?string $scopeId): ScopeProviderInterface
    {
        $provider = $this->createStub(ScopeProviderInterface::class);
        $provider->method('getScopeId')->willReturn($scopeId);

        return $provider;
    }

    private function store(?string $scopeId, ?Config $record): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('supports')->willReturnCallback(static fn(string $key, ?string $requested): bool => $requested === $scopeId);
        $store->method('load')->willReturn($record);
        $store->method('getPriority')->willReturn($scopeId === null ? -100 : 0);

        return $store;
    }

    private function secretBox(): SecretBox
    {
        $secretBox = $this->createStub(SecretBox::class);
        $secretBox->method('encrypt')->willReturnCallback(static fn(string $plain): string => 'enc:' . $plain);

        return $secretBox;
    }

    private function form(string $token, bool $clear): FormInterface
    {
        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn($token);

        $clearField = $this->createStub(FormInterface::class);
        $clearField->method('getData')->willReturn($clear);

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnCallback(
            static fn(string $name): FormInterface => $name === 'bggToken' ? $tokenField : $clearField,
        );

        return $form;
    }
}
