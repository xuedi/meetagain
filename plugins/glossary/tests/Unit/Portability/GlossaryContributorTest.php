<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Portability;

use App\Entity\User;
use App\Portability\ImportContext;
use App\Portability\ImageWriterInterface;
use App\Service\Config\LanguageService;
use App\Portability\ImageImporter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Portability\GlossaryContributor;
use Plugin\Glossary\Repository\GlossaryRepository;
use ReflectionProperty;

class GlossaryContributorTest extends TestCase
{
    public function testExportCarriesTheEntryFieldsAndEveryDefinition(): void
    {
        // Arrange
        $entry = $this->entry(8, '你好')->setDefinition('de', 'hallo');

        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findBy')->willReturn([$entry]);

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([8], $this->createStub(ImageWriterInterface::class));

        // Assert
        self::assertSame([
            'ref' => 8,
            'phrase' => '你好',
            'secondary' => 'nǐ hǎo',
            'term_language' => 'zh',
            'definitions' => ['en' => 'hello', 'de' => 'hallo'],
        ], $rows[0]);
    }

    public function testDuplicatePhraseResolvesToTheExistingEntry(): void
    {
        // Arrange
        $existing = $this->entry(77, '你好');
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $result = $contributor->importItems([['ref' => 8, 'phrase' => '你好', 'definitions' => ['en' => 'hello']]], $this->context());

        // Assert
        self::assertSame([8 => 77], $result->refToItemId);
        self::assertSame(0, $result->created);
        self::assertSame(1, $result->matched);
    }

    public function testUnknownPhraseCreatesTheEntryWithItsDefinitions(): void
    {
        // Arrange
        $persisted = [];
        $contributor = $this->contributor($this->capturingEm($persisted), $this->emptyRepo());
        $rows = [['ref' => 8, 'phrase' => '干嘛', 'secondary' => 'gàn má', 'definitions' => ['en' => 'what is up', 'de' => 'was geht']]];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame([8 => 55], $result->refToItemId);
        self::assertSame(1, $result->created);
        self::assertCount(1, $persisted);
        self::assertSame(['en' => 'what is up', 'de' => 'was geht'], $persisted[0]->getDefinitionMap());
    }

    public function testALegacyArchiveRowLandsInTheSourceLocale(): void
    {
        // Arrange
        $persisted = [];
        $contributor = $this->contributor($this->capturingEm($persisted), $this->emptyRepo());
        $rows = [['ref' => 8, 'phrase' => '干嘛', 'pinyin' => 'gàn má', 'explanation' => 'what is up']];

        // Act
        $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame('gàn má', $persisted[0]->getSecondary());
        self::assertSame(['en' => 'what is up'], $persisted[0]->getDefinitionMap());
    }

    private function contributor(EntityManagerInterface $em, GlossaryRepository $repo): GlossaryContributor
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        return new GlossaryContributor($em, $repo, $languageService);
    }

    private function emptyRepo(): GlossaryRepository
    {
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        return $repo;
    }

    /** @param list<Glossary> $persisted */
    private function capturingEm(array &$persisted): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof Glossary) {
                new ReflectionProperty(Glossary::class, 'id')->setValue($entity, 55);
                $persisted[] = $entity;
            }
        });

        return $em;
    }

    private function entry(int $id, string $phrase): Glossary
    {
        $entry = new Glossary();
        new ReflectionProperty(Glossary::class, 'id')->setValue($entry, $id);
        $entry->setPhrase($phrase);
        $entry->setSecondary('nǐ hǎo');
        $entry->setTermLanguage('zh');
        $entry->setDefinition('en', 'hello');
        $entry->setCreatedBy(1);
        $entry->setCreatedAt(new DateTimeImmutable());

        return $entry;
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->createStub(ImageImporter::class), '/tmp', new User());
    }
}
