<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\Translation;
use App\Repository\TranslationRepository;
use App\Repository\UserRepository;
use App\Service\JustService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class TranslationServiceTest extends TestCase
{
    private MockObject|TranslationRepository $translationRepositoryMock;
    private TranslationService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationRepositoryMock = $this->createMock(TranslationRepository::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $entityManagerInterfaceMock = $this->createMock(EntityManagerInterface::class);
        $filesystemMock = $this->createMock(Filesystem::class);
        $kernelInterfaceMock = $this->createMock(KernelInterface::class);
        $parameterBagInterfaceMock = $this->createMock(ParameterBagInterface::class);
        $justServiceMock = $this->createMock(JustService::class);

        $this->subject = new TranslationService(
            $this->translationRepositoryMock,
            $userRepositoryMock,
            $entityManagerInterfaceMock,
            $filesystemMock,
            $kernelInterfaceMock,
            $parameterBagInterfaceMock,
            $justServiceMock
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

    public function testSaveMatrix(): void
    {
        $dataBase = [

        ];
        $payload = [

        ];

        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('getPayload')
            ->willReturn($payload);

        $this->translationRepositoryMock
            ->expects($this->once())
            ->method('buildKeyValueList')
            ->willReturn($dataBase);

        $this->subject->saveMatrix($request);
    }
}
