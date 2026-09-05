<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Event;

use App\Entity\Event;
use App\Entity\ItemTag;
use App\Item\Tag\ManagedWriter;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Event\DateTagService;
use Symfony\Contracts\Translation\TranslatorInterface;

class DateTagServiceTest extends TestCase
{
    public function testTheDateRowIsResolvedUnderATranslatedRoot(): void
    {
        // Arrange
        $root = new ItemTag();
        $dateTag = new ItemTag();
        $writer = $this->createMock(ManagedWriter::class);
        $writer->expects(static::exactly(2))
            ->method('resolve')
            ->willReturnCallback(static function (string $itemType, array $labels, ?ItemTag $parent) use ($root, $dateTag): ItemTag {
                if ($parent === null) {
                    static::assertSame(['en' => 'Events', 'de' => 'Events'], $labels);

                    return $root;
                }

                static::assertSame($root, $parent);
                static::assertSame(['en' => '2026-08-14'], $labels);

                return $dateTag;
            });
        $writer->expects(static::once())->method('assign')->with($dateTag, 7);

        // Act
        $this->service($writer)->assign($this->event('2026-08-14'), 7);
    }

    public function testTheReadOnlyLookupCreatesNothingWhenTheRootIsMissing(): void
    {
        // Arrange
        $writer = $this->createMock(ManagedWriter::class);
        $writer->expects(static::never())->method('resolve');
        $writer->expects(static::once())->method('find')->willReturn(null);

        // Act & Assert
        static::assertNull($this->service($writer)->findDateTag($this->event('2026-08-14')));
    }

    private function event(string $start): Event
    {
        return new Event()->setStart(new DateTimeImmutable($start));
    }

    private function service(ManagedWriter $writer): DateTagService
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredEnabledCodes')->willReturn(['en', 'de']);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Events');

        return new DateTagService($writer, $languageService, $translator);
    }
}
