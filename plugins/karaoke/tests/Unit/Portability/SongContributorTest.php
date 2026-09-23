<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Portability;

use App\Entity\User;
use App\Portability\ImageImporter;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Entity\LyricLine;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\Portability\SongContributor;
use Plugin\Karaoke\Repository\SongRepository;
use Plugin\Karaoke\ValueObject\MediaLink;
use ReflectionProperty;

class SongContributorTest extends TestCase
{
    public function testAnExportedSongImportsBackWithLinesTimingsAndTranslations(): void
    {
        // Arrange
        $repo = $this->createStub(SongRepository::class);
        $repo->method('findBy')->willReturn([$this->song()]);
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            new ReflectionProperty(Song::class, 'id')->setValue($entity, 77);
            $persisted[] = $entity;
        });
        $contributor = new SongContributor($em, $repo);
        $rows = $contributor->exportItems([12], $this->createStub(ImageWriterInterface::class));

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        static::assertSame([12 => 77], $result->refToItemId);
        static::assertSame(1, $result->created);
        static::assertCount(1, $persisted);
        $song = $persisted[0];
        static::assertInstanceOf(Song::class, $song);
        static::assertSame('茉莉花', $song->getTitle());
        static::assertSame(MediaProvider::YouTube, $song->getMediaProvider());
        static::assertSame('oeRz7Cu97YU', $song->getMediaId());
        static::assertSame(-300, $song->getOffsetMs());
        $lines = $song->getLines()->getValues();
        static::assertSame([0, 1], array_map(static fn(LyricLine $line): int => $line->getPosition(), $lines));
        static::assertSame([12_500, null], array_map(static fn(LyricLine $line): ?int => $line->getStartMs(), $lines));
        static::assertSame('Jasmine flower', $lines[0]->findTranslation('en')?->getText());
        static::assertSame('Jasminblüte', $lines[0]->findTranslation('de')?->getText());
    }

    public function testARowWithAnUnknownProviderOrMalformedIdIsSkipped(): void
    {
        // Arrange
        $contributor = new SongContributor($this->createStub(EntityManagerInterface::class), $this->createStub(SongRepository::class));
        $rows = [
            ['ref' => 1, 'title' => 'A', 'language' => 'zh', 'media' => ['provider' => 'vimeo', 'id' => '123']],
            ['ref' => 2, 'title' => 'B', 'language' => 'zh', 'media' => ['provider' => 'youtube', 'id' => 'short']],
        ];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        static::assertSame([], $result->refToItemId);
        static::assertSame(0, $result->created);
    }

    private function song(): Song
    {
        $song = new Song()
            ->setTitle('茉莉花')
            ->setLanguage('zh')
            ->setMediaLink(new MediaLink(MediaProvider::YouTube, 'oeRz7Cu97YU'))
            ->setOffsetMs(-300)
            ->setCreatedBy(1)
            ->setCreatedAt(new DateTimeImmutable());
        new ReflectionProperty(Song::class, 'id')->setValue($song, 12);

        $first = new LyricLine()
            ->setPosition(0)
            ->setStartMs(12_500)
            ->setText('好一朵美丽的茉莉花');
        $first->setTranslation('en', 'Jasmine flower');
        $first->setTranslation('de', 'Jasminblüte');
        $song->addLine($first);
        $song->addLine(
            new LyricLine()
                ->setPosition(1)
                ->setText('芬芳美丽满枝桠'),
        );

        return $song;
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->createStub(ImageImporter::class), '/tmp', new User());
    }
}
