<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use App\Service\CommandService;
use App\Service\ConfigService;
use App\Service\LanguageService;
use App\Service\TranslationFileManager;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Tests\Unit\Stubs\UserStub;

class TranslationServiceTest extends TestCase
{
    private MockObject|TranslationRepository $translationRepo;
    private MockObject|UserRepository $userRepo;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|TranslationFileManager $fileManager;
    private MockObject|LanguageService $languageService;
    private MockObject|CommandService $commandService;
    private MockObject|ConfigService $configService;
    private TranslationService $subject;

    protected function setUp(): void
    {
        $this->translationRepo = $this->createMock(TranslationRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fileManager = $this->createMock(TranslationFileManager::class);
        $this->languageService = $this->createMock(LanguageService::class);
        $this->commandService = $this->createMock(CommandService::class);
        $this->configService = $this->createMock(ConfigService::class);

        $this->subject = new TranslationService(
            $this->translationRepo,
            $this->entityManager,
            $this->fileManager,
            $this->languageService,
            $this->commandService
        );
    }

    public function testGetMatrixReturnsTranslationsGroupedByPlaceholderAndLanguage(): void
    {
        // Arrange: expected matrix structure sorted alphabetically by placeholder
        $expected = [
            'a_translation' => [
                'de' => ['id' => 1, 'value' => 'Translation A-DE'],
                'en' => ['id' => 2, 'value' => 'Translation A-EN'],
            ],
            'b_translation' => [
                'de' => ['id' => 3, 'value' => 'Translation B-DE'],
                'en' => ['id' => 4, 'value' => ''],
            ],
        ];

        // Arrange: mock repository to return matrix
        $this->translationRepo
            ->expects($this->once())
            ->method('getMatrix')
            ->willReturn($expected);

        // Act: get matrix
        $actual = $this->subject->getMatrix();

        // Assert: matrix is correctly structured and sorted
        $this->assertEquals($expected, $actual);
    }

    public function testSaveMatrixUpdatesExistingTranslation(): void
    {
        // Arrange: existing translation entity
        $translationEntity = (new Translation())
            ->setLanguage('en')
            ->setPlaceholder('key')
            ->setTranslation('old_value');

        $this->translationRepo->method('buildKeyValueList')->willReturn(['1' => 'old_value']);
        $this->translationRepo->method('findOneBy')->with(['id' => '1'])->willReturn($translationEntity);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getPayload')->willReturn(new InputBag(['1' => 'new_value']));

        // Arrange: mock entity manager to verify persist is called
        $this->entityManager->expects($this->once())->method('persist')->with($translationEntity);
        $this->entityManager->expects($this->once())->method('flush');

        // Act: save matrix
        $this->subject->saveMatrix($requestStub);

        // Assert: translation value is updated
        $this->assertSame('new_value', $translationEntity->getTranslation());
    }

    public function testPublishWritesFilesAndClearsCache(): void
    {
        $this->languageService->method('getEnabledCodes')->willReturn(['de']);
        $this->translationRepo->method('findBy')->willReturn([(new Translation())->setPlaceholder('key')->setTranslation('value')]);
        
        $this->fileManager->expects($this->once())->method('cleanUpTranslationFiles');
        $this->fileManager->expects($this->once())->method('writeTranslationFile')->with('de', ['key' => 'value']);
        $this->commandService->expects($this->once())->method('clearCache');
        
        $result = $this->subject->publish();
        $this->assertSame(1, $result['published']);
    }

    public function testGetLanguageCodesReturnsEnabledLocales(): void
    {
        // Arrange: mock language service to return enabled codes
        $this->languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'fr']);

        // Act: get language codes
        $result = $this->subject->getLanguageCodes();

        // Assert: returns enabled locales
        $this->assertSame(['en', 'de', 'fr'], $result);
    }

    public function testIsValidLanguageCodesReturnsTrueForValidCode(): void
    {
        // Arrange: mock language service to validate codes
        $this->languageService->method('isValidCode')->willReturnCallback(
            fn(string $code) => in_array($code, ['en', 'de', 'fr'], true)
        );

        // Act & Assert: valid codes return true
        $this->assertTrue($this->subject->isValidLanguageCodes('en'));
        $this->assertTrue($this->subject->isValidLanguageCodes('de'));
        $this->assertTrue($this->subject->isValidLanguageCodes('fr'));
    }

    public function testIsValidLanguageCodesReturnsFalseForInvalidCode(): void
    {
        // Arrange: mock language service to validate codes
        $this->languageService->method('isValidCode')->willReturnCallback(
            fn(string $code) => in_array($code, ['en', 'de', 'fr'], true)
        );

        // Act & Assert: invalid codes return false
        $this->assertFalse($this->subject->isValidLanguageCodes('es'));
        $this->assertFalse($this->subject->isValidLanguageCodes('it'));
        $this->assertFalse($this->subject->isValidLanguageCodes('xx'));
    }

    public function testGetAltLangListReturnsAlternativeLanguageLinks(): void
    {
        // Arrange: mock language service to return enabled codes
        $this->languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'fr']);

        // Act: get alternative language list for current locale 'en' and URI '/en/events'
        $result = $this->subject->getAltLangList('en', '/en/events');

        // Assert: returns alternative languages with updated URIs (excludes current locale)
        $this->assertArrayNotHasKey('en', $result);
        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('fr', $result);
        $this->assertSame('/de/events', $result['de']);
        $this->assertSame('/fr/events', $result['fr']);
    }

    public function testReplaceUriLanguageCodeReplacesLanguageInUri(): void
    {
        // Arrange: mock language service to return enabled codes
        $this->languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'fr']);

        // Act & Assert: replaces language code in various URI formats
        $this->assertSame('/de/events', $this->subject->replaceUriLanguageCode('/en/events', 'de'));
        $this->assertSame('/fr/events/42', $this->subject->replaceUriLanguageCode('/en/events/42', 'fr'));
        $this->assertSame('/de/events/42/details', $this->subject->replaceUriLanguageCode('/en/events/42/details', 'de'));
    }

    public function testReplaceUriLanguageCodeHandlesJustLanguageUri(): void
    {
        // Arrange: mock language service to return enabled codes
        $this->languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'fr']);

        // Act & Assert: handles URI that is just a language code
        $this->assertSame('/de/', $this->subject->replaceUriLanguageCode('/en/', 'de'));
        $this->assertSame('/fr/', $this->subject->replaceUriLanguageCode('en', 'fr'));
    }

    public function testReplaceUriLanguageCodeReturnsOriginalWhenNoLanguageInUri(): void
    {
        // Arrange: mock language service to return enabled codes
        $this->languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'fr']);

        // Act & Assert: returns original URI when no language code is found
        $this->assertSame('/events', $this->subject->replaceUriLanguageCode('/events', 'de'));
        $this->assertSame('/events/42', $this->subject->replaceUriLanguageCode('/events/42', 'fr'));
    }
}
