<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Contribution\Registry;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\EntityActionDispatcher;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Review\EventChangeTarget;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventChangeTargetTest extends TestCase
{
    public function testEveryTranslatedPropertyOfEveryLocaleBecomesAFieldKey(): void
    {
        // Arrange
        $target = $this->target();
        $event = $this->event(['en' => 'English title', 'de' => 'Deutscher Titel']);

        // Act
        $fields = $target->fieldsFor($event);

        // Assert
        self::assertSame(
            [
                'title_en',
                'teaser_en',
                'description_en',
                'title_de',
                'teaser_de',
                'description_de',
            ],
            $fields,
        );
    }

    public function testAFieldKeyReadsBackTheValueOfItsOwnLocale(): void
    {
        // Arrange
        $target = $this->target();
        $event = $this->event(['en' => 'English title', 'de' => 'Deutscher Titel']);

        // Act
        $values = [$target->currentValue($event, 'title_en'), $target->currentValue($event, 'title_de')];

        // Assert
        self::assertSame(['English title', 'Deutscher Titel'], $values);
    }

    public function testAFieldKeyForALocaleTheEventDoesNotCarryReadsNothing(): void
    {
        // Arrange
        $target = $this->target();
        $event = $this->event(['en' => 'English title']);

        // Act
        $value = $target->currentValue($event, 'title_fr');

        // Assert
        self::assertNull($value);
    }

    #[DataProvider('provideFieldLabels')]
    public function testTheFieldLabelNamesThePropertyAndTheLocale(string $field, string $expected): void
    {
        // Arrange
        $target = $this->target();

        // Act
        $label = $target->getFieldLabel($field);

        // Assert
        self::assertSame($expected, $label);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFieldLabels(): iterable
    {
        yield 'a title carries its uppercased locale' => ['title_en', 'contribution.event_field_title|EN'];
        yield 'a teaser carries its uppercased locale' => ['teaser_de', 'contribution.event_field_teaser|DE'];
        yield 'a description carries its uppercased locale' => ['description_fr', 'contribution.event_field_description|FR'];
        yield 'a property outside the translated text falls back to the raw key' => ['start_en', 'start_en'];
    }

    private function target(): EventChangeTarget
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn(string $id, array $parameters = []): string => $id . ($parameters === [] ? '' : '|' . implode(',', $parameters)));

        return new EventChangeTarget(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(EventRepository::class),
            $this->createStub(EventFilterService::class),
            $this->createStub(Registry::class),
            $this->createStub(EntityActionDispatcher::class),
            $this->createStub(Security::class),
            $this->createStub(RouterInterface::class),
            $translator,
        );
    }

    /**
     * @param array<string, string> $titlesByLocale
     */
    private function event(array $titlesByLocale): Event
    {
        $event = new Event();
        foreach ($titlesByLocale as $locale => $title) {
            $translation = new EventTranslation();
            $translation->setLanguage($locale);
            $translation->setTitle($title);
            $event->addTranslation($translation);
        }

        return $event;
    }
}
