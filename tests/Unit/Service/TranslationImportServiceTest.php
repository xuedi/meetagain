<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use App\Service\CommandService;
use App\Service\ConfigService;
use App\Service\TranslationFileManager;
use App\Service\TranslationImportService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

class TranslationImportServiceTest extends TestCase
{
    private MockObject|TranslationRepository $translationRepo;
    private MockObject|UserRepository $userRepo;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|TranslationFileManager $fileManager;
    private MockObject|CommandService $commandService;
    private MockObject|ConfigService $configService;
    private MockObject|TranslationService $translationService;
    private TranslationImportService $subject;

    protected function setUp(): void
    {
        $this->translationRepo = $this->createStub(TranslationRepository::class);
        $this->userRepo = $this->createStub(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fileManager = $this->createStub(TranslationFileManager::class);
        $this->commandService = $this->createStub(CommandService::class);
        $this->configService = $this->createStub(ConfigService::class);
        $this->translationService = $this->createStub(TranslationService::class);

        $this->subject = new TranslationImportService(
            $this->translationRepo,
            $this->userRepo,
            $this->entityManager,
            $this->fileManager,
            $this->commandService,
            $this->configService,
            $this->translationService
        );
    }

    public function testExtractProcessesFilesCorrectly(): void
    {
        $importUser = (new UserStub())->setId(1);
        $this->userRepo->method('findOneBy')->willReturn($importUser);
        $this->configService->method('getSystemUserId')->willReturn(1);

        $file = $this->createStub(\Symfony\Component\Finder\SplFileInfo::class);
        $file->method('getFilename')->willReturn('messages.de.php');
        $file->method('getPathname')->willReturn(__DIR__ . '/Stubs/translations_stub.php');

        $this->fileManager->method('getTranslationFiles')->willReturn([$file]);
        $this->translationRepo->method('getUniqueList')->willReturn(['de' => []]);

        $this->entityManager->expects($this->atLeastOnce())->method('persist');
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $result = $this->subject->extract();
        $this->assertArrayHasKey('count', $result);
    }

    public function testImportForLocalDevelopmentBatchesCorrectly(): void
    {
        $importUser = (new UserStub())->setId(1);
        $this->userRepo->method('findOneBy')->willReturn($importUser);
        $this->configService->method('getSystemUserId')->willReturn(1);

        $connMock = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->entityManager->method('getConnection')->willReturn($connMock);
        $connMock->expects($this->exactly(2))->method('executeStatement');

        $data = [];
        for ($i = 0; $i < 250; ++$i) {
            $data[] = [
                'language' => 'en',
                'placeholder' => 'test' . $i,
                'translation' => 'Test ' . $i,
            ];
        }

        $apiUrl = 'data:application/json;base64,' . base64_encode(json_encode($data));

        $this->entityManager->expects($this->exactly(250))->method('persist');
        // 250 items, batch size 100:
        // i=100 -> flush, clear
        // i=200 -> flush, clear
        // final -> flush, clear
        $this->entityManager->expects($this->exactly(3))->method('flush');
        $this->entityManager->expects($this->exactly(3))->method('clear');

        $this->subject->importForLocalDevelopment($apiUrl);
    }
}
