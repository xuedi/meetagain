<?php declare(strict_types=1);

namespace Tests\Unit\DataFixtures;

use App\DataFixtures\ActivityFixture;
use App\DataFixtures\CmsBlockFixture;
use App\DataFixtures\CmsFixture;
use App\DataFixtures\ConfigFixture;
use App\DataFixtures\EmailTemplateFixture;
use App\DataFixtures\EventFixture;
use App\DataFixtures\HostFixture;
use App\DataFixtures\LanguageFixture;
use App\DataFixtures\LocationFixture;
use App\DataFixtures\MenuFixture;
use App\DataFixtures\MessageFixture;
use App\DataFixtures\SystemUserFixture;
use App\DataFixtures\UserFixture;
use App\Repository\UserRepository;
use App\Service\EmailTemplateService;
use App\Service\ImageService;
use App\Service\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Bookclub\DataFixtures\BookclubFixture;
use Plugin\Dishes\DataFixtures\DishFixture;
use Plugin\Filmclub\DataFixtures\FilmFixture;
use Plugin\Glossary\DataFixtures\GlossaryFixture;
use Plugin\MultiSite\DataFixtures\GroupCmsBlockFixture;
use Plugin\MultiSite\DataFixtures\GroupCmsFixture;
use Plugin\MultiSite\DataFixtures\GroupCmsSettingsFixture;
use Plugin\MultiSite\DataFixtures\GroupEventFixture;
use Plugin\MultiSite\DataFixtures\GroupFixture;
use Plugin\MultiSite\DataFixtures\GroupInvitationFixture;
use Plugin\MultiSite\DataFixtures\GroupMemberFixture;
use Plugin\MultiSite\DataFixtures\GroupMenuFixture;
use Plugin\MultiSite\DataFixtures\MessageFixture as MultiSiteMessageFixture;
use Plugin\MultiSite\Repository\GroupMenuMappingRepository;
use Plugin\MultiSite\Service\GroupCmsService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Smoke tests to verify all fixture classes can be instantiated without syntax errors.
 * This does NOT test fixture logic, only that the PHP code is syntactically valid.
 */
class FixtureSmokeTest extends TestCase
{
    // Base Fixtures - Install Group

    public function testSystemUserFixtureCanInstantiate(): void
    {
        // Arrange
        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        // Act
        $fixture = new SystemUserFixture($hasher);

        // Assert
        $this->assertInstanceOf(SystemUserFixture::class, $fixture);
    }

    public function testLanguageFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new LanguageFixture($imageService);

        // Assert
        $this->assertInstanceOf(LanguageFixture::class, $fixture);
    }

    public function testConfigFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new ConfigFixture();

        // Assert
        $this->assertInstanceOf(ConfigFixture::class, $fixture);
    }

    public function testEmailTemplateFixtureCanInstantiate(): void
    {
        // Arrange
        $templateService = $this->createMock(EmailTemplateService::class);
        $languageService = $this->createMock(LanguageService::class);

        // Act
        $fixture = new EmailTemplateFixture($templateService, $languageService);

        // Assert
        $this->assertInstanceOf(EmailTemplateFixture::class, $fixture);
    }

    // Base Fixtures - Base Group

    public function testUserFixtureCanInstantiate(): void
    {
        // Arrange
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new UserFixture($hasher, $imageService);

        // Assert
        $this->assertInstanceOf(UserFixture::class, $fixture);
    }

    public function testLocationFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new LocationFixture();

        // Assert
        $this->assertInstanceOf(LocationFixture::class, $fixture);
    }

    public function testHostFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new HostFixture();

        // Assert
        $this->assertInstanceOf(HostFixture::class, $fixture);
    }

    public function testCmsFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new CmsFixture();

        // Assert
        $this->assertInstanceOf(CmsFixture::class, $fixture);
    }

    public function testCmsBlockFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new CmsBlockFixture($imageService);

        // Assert
        $this->assertInstanceOf(CmsBlockFixture::class, $fixture);
    }

    public function testMenuFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new MenuFixture();

        // Assert
        $this->assertInstanceOf(MenuFixture::class, $fixture);
    }

    public function testEventFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new EventFixture($imageService);

        // Assert
        $this->assertInstanceOf(EventFixture::class, $fixture);
    }

    public function testActivityFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new ActivityFixture();

        // Assert
        $this->assertInstanceOf(ActivityFixture::class, $fixture);
    }

    public function testMessageFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new MessageFixture();

        // Assert
        $this->assertInstanceOf(MessageFixture::class, $fixture);
    }

    // Plugin Fixtures - MultiSite

    public function testGroupFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new GroupFixture();

        // Assert
        $this->assertInstanceOf(GroupFixture::class, $fixture);
    }

    public function testGroupMemberFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new GroupMemberFixture();

        // Assert
        $this->assertInstanceOf(GroupMemberFixture::class, $fixture);
    }

    public function testGroupInvitationFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new GroupInvitationFixture();

        // Assert
        $this->assertInstanceOf(GroupInvitationFixture::class, $fixture);
    }

    public function testGroupCmsFixtureCanInstantiate(): void
    {
        // Arrange
        $groupCmsService = $this->createMock(GroupCmsService::class);

        // Act
        $fixture = new GroupCmsFixture($groupCmsService);

        // Assert
        $this->assertInstanceOf(GroupCmsFixture::class, $fixture);
    }

    public function testGroupCmsBlockFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new GroupCmsBlockFixture($imageService);

        // Assert
        $this->assertInstanceOf(GroupCmsBlockFixture::class, $fixture);
    }

    public function testGroupMenuFixtureCanInstantiate(): void
    {
        // Arrange
        $menuMappingRepository = $this->createMock(GroupMenuMappingRepository::class);

        // Act
        $fixture = new GroupMenuFixture($menuMappingRepository);

        // Assert
        $this->assertInstanceOf(GroupMenuFixture::class, $fixture);
    }

    public function testGroupEventFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);

        // Act
        $fixture = new GroupEventFixture($imageService);

        // Assert
        $this->assertInstanceOf(GroupEventFixture::class, $fixture);
    }

    public function testGroupCmsSettingsFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new GroupCmsSettingsFixture();

        // Assert
        $this->assertInstanceOf(GroupCmsSettingsFixture::class, $fixture);
    }

    public function testMultiSiteMessageFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new MultiSiteMessageFixture();

        // Assert
        $this->assertInstanceOf(MultiSiteMessageFixture::class, $fixture);
    }

    // Plugin Fixtures - Other Plugins

    public function testDishFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $userRepository = $this->createMock(UserRepository::class);

        // Act
        $fixture = new DishFixture($imageService, $userRepository);

        // Assert
        $this->assertInstanceOf(DishFixture::class, $fixture);
    }

    public function testBookclubFixtureCanInstantiate(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $userRepository = $this->createMock(UserRepository::class);

        // Act
        $fixture = new BookclubFixture($imageService, $userRepository);

        // Assert
        $this->assertInstanceOf(BookclubFixture::class, $fixture);
    }

    public function testFilmFixtureCanInstantiate(): void
    {
        // Arrange
        $userRepository = $this->createMock(UserRepository::class);

        // Act
        $fixture = new FilmFixture($userRepository);

        // Assert
        $this->assertInstanceOf(FilmFixture::class, $fixture);
    }

    public function testGlossaryFixtureCanInstantiate(): void
    {
        // Act
        $fixture = new GlossaryFixture();

        // Assert
        $this->assertInstanceOf(GlossaryFixture::class, $fixture);
    }
}
