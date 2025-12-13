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

    private function createSubject(
        ?TranslationRepository $translationRepo = null,
        ?EntityManagerInterface $entityManager = null,
    ): TranslationService {
        return new TranslationService(
            translationRepo: $translationRepo ?? $this->createStub(TranslationRepository::class),
            userRepo: $this->createStub(UserRepository::class),
            entityManager: $entityManager ?? $this->createStub(EntityManagerInterface::class),
            fs: $this->createStub(Filesystem::class),
            appParams: $this->createStub(ParameterBagInterface::class),
            commandService: $this->createStub(CommandService::class),
            configService: $this->createStub(ConfigService::class),
            kernelProjectDir: __DIR__ . '/tmp/',
        );
    }
}
