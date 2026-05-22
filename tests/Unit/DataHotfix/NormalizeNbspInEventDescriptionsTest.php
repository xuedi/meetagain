<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\NormalizeNbspInEventDescriptions;
use App\Entity\EventTranslation;
use App\Repository\EventTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NormalizeNbspInEventDescriptionsTest extends TestCase
{
    public function testReplacesNbspCharAndEntityAcrossAllStringFields(): void
    {
        // Arrange
        $nbspChar = "\u{00A0}";
        $translation = new EventTranslation();
        $translation->setTitle("Title{$nbspChar}with&nbsp;both");
        $translation->setTeaser("Teaser{$nbspChar}text");
        $translation->setDescription("<p>Body&nbsp;text{$nbspChar}here</p>");

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $subject = new NormalizeNbspInEventDescriptions($this->makeRepoReturning([$translation]), $em);

        // Act
        $subject->execute();

        // Assert
        static::assertSame('Title with both', $translation->getTitle());
        static::assertSame('Teaser text', $translation->getTeaser());
        static::assertSame('<p>Body text here</p>', $translation->getDescription());
    }

    public function testSkipsCleanRowsWithoutWriting(): void
    {
        // Arrange
        $clean = new EventTranslation();
        $clean->setTitle('clean title');
        $clean->setTeaser('clean teaser');
        $clean->setDescription('<p>clean body</p>');
        $originalTitle = $clean->getTitle();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $subject = new NormalizeNbspInEventDescriptions($this->makeRepoReturning([$clean]), $em);

        // Act
        $subject->execute();

        // Assert
        static::assertSame($originalTitle, $clean->getTitle(), 'clean row must be untouched');
    }

    public function testIdentifierIsDatePrefixedAndUnique(): void
    {
        // Arrange
        $subject = new NormalizeNbspInEventDescriptions(
            $this->createStub(EventTranslationRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        // Act
        $id = $subject->getIdentifier();

        // Assert
        static::assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_/', $id);
    }

    private function makeRepoReturning(array $translations): EventTranslationRepository
    {
        $repo = $this->createStub(EventTranslationRepository::class);
        $repo->method('iterateAll')->willReturn(new \ArrayIterator($translations));

        return $repo;
    }
}
