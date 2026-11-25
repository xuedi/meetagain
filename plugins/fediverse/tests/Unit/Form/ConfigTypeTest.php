<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Form;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\Form\ConfigType;
use Plugin\Fediverse\Publisher\PluginSettings\ConfigDescriptor;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigTypeTest extends TestCase
{
    /** @return Generator<string, array{string, bool}> */
    public static function usernames(): Generator
    {
        yield 'lowercase letters, digits and underscores' => ['chess_club_42', true];
        yield 'an uppercase letter' => ['ChessClub', false];
        yield 'a hyphen' => ['chess-club', false];
        yield 'a dot' => ['chess.club', false];
        yield 'an at sign' => ['@chess', false];
        yield 'thirty characters' => [str_repeat('a', Config::USERNAME_MAX), true];
        yield 'thirty-one characters' => [str_repeat('a', Config::USERNAME_MAX + 1), false];
    }

    #[DataProvider('usernames')]
    public function testTheUsernameRule(string $username, bool $valid): void
    {
        // Arrange
        $form = $this->form(new Config(), null, new Config());

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'username' => $username]);

        // Assert
        static::assertSame($valid, $form->get('username')->isValid());
    }

    public function testTheGlobalFormOffersEveryChoiceAndTheGlobalOnlyFields(): void
    {
        // Act
        $form = $this->form(new Config(), null, new Config()->setContent(Content::Title));

        // Assert
        static::assertSame(Content::cases(), $form->get('content')->getConfig()->getOption('choices'));
        static::assertTrue($form->has('image'));
        static::assertTrue($form->has('announce'));
        static::assertTrue($form->has('blockedServers'));
    }

    public function testTheScopedFormOffersNothingAboveTheCeiling(): void
    {
        // Arrange
        $ceiling = new Config()
            ->setContent(Content::Summary)
            ->setLocation(Location::City)
            ->setImage(false);

        // Act
        $form = $this->form(new Config(), '7', $ceiling);

        // Assert
        static::assertSame([Content::Title, Content::Summary], $form->get('content')->getConfig()->getOption('choices'));
        static::assertSame([Location::None, Location::City], $form->get('location')->getConfig()->getOption('choices'));
        static::assertFalse($form->has('image'));
        static::assertFalse($form->has('announce'));
        static::assertFalse($form->has('blockedServers'));
    }

    public function testTheScopedFormShowsAStoredValueAboveATightenedCeilingAtTheCeiling(): void
    {
        // Arrange
        $data = new Config()
            ->setContent(Content::Full)
            ->setLocation(Location::Address);

        // Act
        $this->form(
            $data,
            '7',
            new Config()
                ->setContent(Content::Title)
                ->setLocation(Location::None),
        );

        // Assert
        static::assertSame(Content::Title, $data->getContent());
        static::assertSame(Location::None, $data->getLocation());
    }

    public function testTheScopedFormDoesNotOfferTheInstallationAccountAsItsOwn(): void
    {
        // Arrange
        $data = new Config()
            ->setUsername('events')
            ->setAccountSummary('All events.');

        // Act
        $this->form($data, '7', new Config()->setUsername('events'));

        // Assert
        static::assertNull($data->getUsername());
        static::assertNull($data->getAccountSummary());
    }

    public function testBlockedServersAreReadOnePerLineAndValidated(): void
    {
        // Arrange
        $form = $this->form(new Config(), null, new Config());

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'blockedServers' => " Spam.Example \n\nbad.social\n"]);

        // Assert
        static::assertTrue($form->isValid());
        static::assertSame(['spam.example', 'bad.social'], $form->getData()->getBlockedServers());
    }

    public function testABlockedServerThatIsNoDomainIsRefused(): void
    {
        // Arrange
        $form = $this->form(new Config(), null, new Config());

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'blockedServers' => 'https://bad.social/about']);

        // Assert
        static::assertFalse($form->get('blockedServers')->isValid());
    }

    public function testAUsernameTakenElsewhereIsRefused(): void
    {
        // Arrange
        $form = $this->form(new Config(), '7', new Config(), null, ['events']);

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'username' => 'events']);

        // Assert
        static::assertFalse($form->get('username')->isValid());
    }

    public function testALockedAccountKeepsItsUsername(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'))->lock(new DateTimeImmutable('2026-11-02'));
        $data = new Config()->setUsername('chess_club');
        $form = $this->form($data, '7', new Config(), $account);

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'username' => 'renamed']);

        // Assert
        static::assertTrue($form->get('username')->isDisabled());
        static::assertSame('chess_club', $data->getUsername());
    }

    public function testAnUnlockedAccountMayStillBeRenamed(): void
    {
        // Arrange
        $account = new Account('7', 'chess_club', 'public', 'private', new DateTimeImmutable('2026-11-01'));
        $data = new Config()->setUsername('chess_club');
        $form = $this->form($data, '7', new Config(), $account);

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', 'username' => 'renamed']);

        // Assert
        static::assertTrue($form->isValid());
        static::assertSame('renamed', $data->getUsername());
    }

    public function testSwitchingSharingOnNeedsAUsername(): void
    {
        // Arrange
        $form = $this->form(new Config(), '7', new Config());

        // Act
        $form->submit(['content' => 'summary', 'location' => 'city', ConfigType::SWITCH_ON => '']);

        // Assert
        static::assertFalse($form->get('username')->isValid());
    }

    /** @return Generator<string, array{bool, ?string, bool}> */
    public static function switches(): Generator
    {
        yield 'switched on' => [false, ConfigType::SWITCH_ON, true];
        yield 'switched off' => [true, ConfigType::SWITCH_OFF, false];
        yield 'a plain save keeps it on' => [true, null, true];
        yield 'a plain save keeps it off' => [false, null, false];
    }

    #[DataProvider('switches')]
    public function testTheSwitchSetsSharingAndAPlainSaveLeavesItAlone(bool $before, ?string $clicked, bool $after): void
    {
        // Arrange
        $data = new Config()
            ->setUsername('chess_club')
            ->setActive($before);
        $form = $this->form($data, '7', new Config());
        $submitted = ['content' => 'summary', 'location' => 'city', 'username' => 'chess_club'];
        if ($clicked !== null) {
            $submitted[$clicked] = '';
        }

        // Act
        $form->submit($submitted);
        new ConfigDescriptor(
            $this->createStub(AccountRepository::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(TranslatorInterface::class),
        )->applyForm($data, $form);

        // Assert
        static::assertTrue($form->isValid());
        static::assertSame($after, $data->isActive());
    }

    /** @param list<string> $takenUsernames */
    private function form(Config $data, ?string $scopeId, Config $ceiling, ?Account $account = null, array $takenUsernames = []): FormInterface
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getCeiling')->willReturn($ceiling);

        $accounts = $this->createStub(AccountRepository::class);
        $accounts->method('findLiveForScope')->willReturn($account);
        $accounts->method('isUsernameTakenOutsideScope')->willReturnCallback(static fn(string $username): bool => in_array($username, $takenUsernames, true));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([new ConfigType($configService, $accounts)], []))
            ->getFormFactory()
            ->create(ConfigType::class, $data, ['scope_id' => $scopeId]);
    }
}
