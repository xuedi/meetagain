<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use App\Service\CommandService;
use App\Service\ConfigService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

class TranslationServiceTest extends TestCase
{
    public function testGetMatrixReturnsTranslationsGroupedByPlaceholderAndLanguage(): void
    {
        // Arrange: expected matrix structure sorted alphabetically by placeholder
        $expected = [
            'a_translation' => [
                'de' => ['id' => null, 'value' => 'Translation A-DE'],
                'en' => ['id' => null, 'value' => 'Translation A-EN'],
            ],
            'b_translation' => [
                'de' => ['id' => null, 'value' => 'Translation B-DE'],
                'en' => ['id' => null, 'value' => ''],
            ],
        ];

        // Arrange: mock repository to return translations in random order
        $repoMock = $this->createMock(TranslationRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([
                (new Translation())->setLanguage('de')->setPlaceholder('b_translation')->setTranslation('Translation B-DE'),
                (new Translation())->setLanguage('de')->setPlaceholder('a_translation')->setTranslation('Translation A-DE'),
                (new Translation())->setLanguage('en')->setPlaceholder('b_translation')->setTranslation(null),
                (new Translation())->setLanguage('en')->setPlaceholder('a_translation')->setTranslation('Translation A-EN'),
            ]);

        $subject = $this->createSubject(translationRepo: $repoMock);

        // Act: get matrix
        $actual = $subject->getMatrix();

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

        $repoStub = $this->createStub(TranslationRepository::class);
        $repoStub->method('buildKeyValueList')->willReturn(['1' => 'old_value']);
        $repoStub->method('findOneBy')->with(['id' => '1'])->willReturn($translationEntity);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getPayload')->willReturn(new InputBag(['1' => 'new_value']));

        // Arrange: mock entity manager to verify persist is called
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($translationEntity);
        $emMock->expects($this->once())->method('flush');

        $subject = $this->createSubject(translationRepo: $repoStub, entityManager: $emMock);

        // Act: save matrix
        $subject->saveMatrix($requestStub);

        // Assert: translation value is updated
        $this->assertSame('new_value', $translationEntity->getTranslation());
    }

    public function testSaveMatrixIgnoresEmptyTranslations(): void
    {
        // Arrange: empty translation value in request
        $repoStub = $this->createStub(TranslationRepository::class);
        $repoStub->method('buildKeyValueList')->willReturn(['1' => 'existing_value']);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getPayload')->willReturn(new InputBag(['1' => '']));

        // Arrange: mock entity manager to verify persist is NOT called
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $subject = $this->createSubject(translationRepo: $repoStub, entityManager: $emMock);

        // Act: save matrix with empty value
        $subject->saveMatrix($requestStub);
    }

    public function testSaveMatrixSkipsNonExistingTranslationKey(): void
    {
        // Arrange: translation key not found in repository
        $repoStub = $this->createStub(TranslationRepository::class);
        $repoStub->method('buildKeyValueList')->willReturn([]);
        $repoStub->method('findOneBy')->willReturn(null);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getPayload')->willReturn(new InputBag(['1' => 'new_value']));

        // Arrange: mock entity manager to verify persist is NOT called
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $emMock->expects($this->once())->method('flush');

        $subject = $this->createSubject(translationRepo: $repoStub, entityManager: $emMock);

        // Act: save matrix with non-existing key
        $subject->saveMatrix($requestStub);
    }

    public function testSaveMatrixPersistsAndFlushesChanges(): void
    {
        // Arrange: existing translation entity
        $translationEntity = (new Translation())
            ->setLanguage('fr')
            ->setPlaceholder('placeholder')
            ->setTranslation('old_value');

        $repoStub = $this->createStub(TranslationRepository::class);
        $repoStub->method('buildKeyValueList')->willReturn(['1' => 'old_value']);
        $repoStub->method('findOneBy')->with(['id' => '1'])->willReturn($translationEntity);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('getPayload')->willReturn(new InputBag(['1' => 'updated_value']));

        // Arrange: mock entity manager to verify persist and flush
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('persist')->with($translationEntity);
        $emMock->expects($this->once())->method('flush');

        $subject = $this->createSubject(translationRepo: $repoStub, entityManager: $emMock);

        // Act: save matrix
        $subject->saveMatrix($requestStub);

        // Assert: translation value is updated
        $this->assertSame('updated_value', $translationEntity->getTranslation());
    }

    public function testGetLanguageCodesReturnsEnabledLocales(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act: get language codes
        $result = $subject->getLanguageCodes();

        // Assert: returns enabled locales
        $this->assertSame(['en', 'de', 'fr'], $result);
    }

    public function testIsValidLanguageCodesReturnsTrueForValidCode(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act & Assert: valid codes return true
        $this->assertTrue($subject->isValidLanguageCodes('en'));
        $this->assertTrue($subject->isValidLanguageCodes('de'));
        $this->assertTrue($subject->isValidLanguageCodes('fr'));
    }

    public function testIsValidLanguageCodesReturnsFalseForInvalidCode(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act & Assert: invalid codes return false
        $this->assertFalse($subject->isValidLanguageCodes('es'));
        $this->assertFalse($subject->isValidLanguageCodes('it'));
        $this->assertFalse($subject->isValidLanguageCodes('xx'));
    }

    public function testGetAltLangListReturnsAlternativeLanguageLinks(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act: get alternative language list for current locale 'en' and URI '/en/events'
        $result = $subject->getAltLangList('en', '/en/events');

        // Assert: returns alternative languages with updated URIs (excludes current locale)
        $this->assertArrayNotHasKey('en', $result);
        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('fr', $result);
        $this->assertSame('/de/events', $result['de']);
        $this->assertSame('/fr/events', $result['fr']);
    }

    public function testReplaceUriLanguageCodeReplacesLanguageInUri(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act & Assert: replaces language code in various URI formats
        $this->assertSame('/de/events', $subject->replaceUriLanguageCode('/en/events', 'de'));
        $this->assertSame('/fr/events/42', $subject->replaceUriLanguageCode('/en/events/42', 'fr'));
        $this->assertSame('/de/events/42/details', $subject->replaceUriLanguageCode('/en/events/42/details', 'de'));
    }

    public function testReplaceUriLanguageCodeHandlesJustLanguageUri(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act & Assert: handles URI that is just a language code
        $this->assertSame('/de/', $subject->replaceUriLanguageCode('/en/', 'de'));
        $this->assertSame('/fr/', $subject->replaceUriLanguageCode('en', 'fr'));
    }

    public function testReplaceUriLanguageCodeReturnsOriginalWhenNoLanguageInUri(): void
    {
        // Arrange: mock parameter bag to return enabled locales
        $paramsBagStub = $this->createStub(ParameterBagInterface::class);
        $paramsBagStub->method('get')->with('kernel.enabled_locales')->willReturn(['en', 'de', 'fr']);

        $subject = $this->createSubject(appParams: $paramsBagStub);

        // Act & Assert: returns original URI when no language code is found
        $this->assertSame('/events', $subject->replaceUriLanguageCode('/events', 'de'));
        $this->assertSame('/events/42', $subject->replaceUriLanguageCode('/events/42', 'fr'));
    }

    private function createSubject(
        ?TranslationRepository $translationRepo = null,
        ?EntityManagerInterface $entityManager = null,
        ?ParameterBagInterface $appParams = null,
    ): TranslationService {
        return new TranslationService(
            translationRepo: $translationRepo ?? $this->createStub(TranslationRepository::class),
            userRepo: $this->createStub(UserRepository::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
            fs: $this->createStub(Filesystem::class),
            appParams: $appParams ?? $this->createStub(ParameterBagInterface::class),
            commandService: $this->createStub(CommandService::class),
            configService: $this->createStub(ConfigService::class),
            kernelProjectDir: __DIR__ . '/tmp/',
        );
    }
}
