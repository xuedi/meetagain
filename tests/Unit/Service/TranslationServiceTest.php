<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use App\Service\CommandService;
use App\Service\ConfigService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

class TranslationServiceTest extends TestCase
{
    private MockObject|TranslationRepository $translationRepositoryMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|CommandService $commandServiceMock;
    private MockObject|Request $requestMock;
    private TranslationService $subject;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->requestMock = $this->createMock(Request::class);
        $this->translationRepositoryMock = $this->createMock(TranslationRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->commandServiceMock = $this->createMock(CommandService::class);

        $this->subject = new TranslationService(
            $this->translationRepositoryMock,
            $this->createMock(UserRepository::class),
            $this->entityManagerMock,
            $this->createMock(Filesystem::class),
            $this->createMock(ParameterBagInterface::class),
            $this->commandServiceMock,
            $this->createMock(ConfigService::class),
            __DIR__ . '/tmp/'
        );
    }

    public function testGetMatrix(): void
    {
        $expected = [
            'a_translation' => [
                'de' => [
                    'id' => null,
                    'value' => 'Translation A-DE',
                ],
                'en' => [
                    'id' => null,
                    'value' => 'Translation A-EN',
                ],
            ],
            'b_translation' => [
                'de' => [
                    'id' => null,
                    'value' => 'Translation B-DE',
                ],
                'en' => [
                    'id' => null,
                    'value' => '',
                ],
            ],
        ];

        $this->translationRepositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([
                new Translation()
                    ->setLanguage('de')
                    ->setPlaceholder('b_translation')
                    ->setTranslation('Translation B-DE'),
                new Translation()
                    ->setLanguage('de')
                    ->setPlaceholder('a_translation')
                    ->setTranslation('Translation A-DE'),
                new Translation()
                    ->setLanguage('en')
                    ->setPlaceholder('b_translation')
                    ->setTranslation(null),
                new Translation()
                    ->setLanguage('en')
                    ->setPlaceholder('a_translation')
                    ->setTranslation('Translation A-EN'),
            ]);

        $actual = $this->subject->getMatrix();

        $this->assertEquals($expected, $actual);
    }

    public function testSaveMatrixUpdatesExistingTranslation(): void
    {
        $translationEntity = new Translation()
            ->setLanguage('en')
            ->setPlaceholder('key')
            ->setTranslation('old_value');

        $this->translationRepositoryMock
            ->method('buildKeyValueList')
            ->willReturn(['1' => 'old_value']);

        $this->translationRepositoryMock
            ->method('findOneBy')
            ->with(['id' => '1'])
            ->willReturn($translationEntity);

        $this->requestMock
            ->method('getPayload')
            ->willReturn(new InputBag(['1' => 'new_value']));

        $this->entityManagerMock
            ->expects($this->once())->
            method('persist')
            ->with($translationEntity);

        $this->entityManagerMock
            ->expects($this->once())->
            method('flush');

        $this->subject->saveMatrix($this->requestMock);

        $this->assertEquals('new_value', $translationEntity->getTranslation());
    }

    public function testSaveMatrixIgnoresEmptyTranslations(): void
    {
        $this->translationRepositoryMock
            ->method('buildKeyValueList')
            ->willReturn(['1' => 'existing_value']);

        $this->requestMock
            ->method('getPayload')
            ->willReturn(new InputBag(['1' => '']));

        $this->entityManagerMock
            ->expects($this->never())
            ->method('persist');

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->subject->saveMatrix($this->requestMock);
    }

    public function testSaveMatrixSkipsNonExistingTranslationKey(): void
    {
        $this->translationRepositoryMock
            ->method('buildKeyValueList')
            ->willReturn([]);
        $this->translationRepositoryMock
            ->method('findOneBy')
            ->willReturn(null);

        $this->requestMock
            ->method('getPayload')
            ->willReturn(new InputBag(['1' => 'new_value']));

        $this->entityManagerMock
            ->expects($this->never())
            ->method('persist');

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->subject->saveMatrix($this->requestMock);
    }

    public function testSaveMatrixPersistsAndFlushesChanges(): void
    {
        $translationEntity = (new Translation())
            ->setLanguage('fr')
            ->setPlaceholder('placeholder')
            ->setTranslation('old_value');

        $this->translationRepositoryMock
            ->method('buildKeyValueList')
            ->willReturn(['1' => 'old_value']);

        $this->translationRepositoryMock
            ->method('findOneBy')
            ->with(['id' => '1'])
            ->willReturn($translationEntity);

        $this->requestMock
            ->method('getPayload')
            ->willReturn(new InputBag(['1' => 'updated_value']));

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($translationEntity);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->subject->saveMatrix($this->requestMock);

        $this->assertEquals('updated_value', $translationEntity->getTranslation());
    }
}
