<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Portability;

use App\Entity\User;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\System\PortableImageImporter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Portability\GlossaryPortabilityContributor;
use Plugin\Glossary\Repository\GlossaryRepository;
use ReflectionProperty;

class GlossaryPortabilityContributorTest extends TestCase
{
    public function testExportCarriesTheEntryFields(): void
    {
        // Arrange
        $entry = $this->entry(8, '你好');

        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findBy')->willReturn([$entry]);

        $contributor = new GlossaryPortabilityContributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([8], $this->createStub(PortableImageWriterInterface::class));

        // Assert
        self::assertSame(['ref' => 8, 'phrase' => '你好', 'pinyin' => 'nǐ hǎo', 'explanation' => 'hello', 'approved' => true], $rows[0]);
    }

    public function testDuplicatePhraseResolvesToTheExistingEntry(): void
    {
        // Arrange
        $existing = $this->entry(77, '你好');
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $contributor = new GlossaryPortabilityContributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $result = $contributor->importItems([['ref' => 8, 'phrase' => '你好', 'explanation' => 'hello']], $this->context());

        // Assert
        self::assertSame([8 => 77], $result->refToItemId);
        self::assertSame(0, $result->created);
        self::assertSame(1, $result->matched);
    }

    public function testUnknownPhraseCreatesTheEntry(): void
    {
        // Arrange
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
            if ($entity instanceof Glossary) {
                new ReflectionProperty(Glossary::class, 'id')->setValue($entity, 55);
            }
        });

        $contributor = new GlossaryPortabilityContributor($em, $repo);
        $rows = [['ref' => 8, 'phrase' => '干嘛', 'pinyin' => 'gàn má', 'explanation' => 'what is up', 'approved' => false]];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame([8 => 55], $result->refToItemId);
        self::assertSame(1, $result->created);
        self::assertCount(1, $persisted);
        self::assertFalse($persisted[0]->getApproved());
    }

    private function entry(int $id, string $phrase): Glossary
    {
        $entry = new Glossary();
        new ReflectionProperty(Glossary::class, 'id')->setValue($entry, $id);
        $entry->setPhrase($phrase);
        $entry->setPinyin('nǐ hǎo');
        $entry->setExplanation('hello');
        $entry->setApproved(true);
        $entry->setCreatedBy(1);
        $entry->setCreatedAt(new DateTimeImmutable());

        return $entry;
    }

    private function context(): ItemImportContext
    {
        return new ItemImportContext($this->createStub(PortableImageImporter::class), '/tmp', new User());
    }
}
