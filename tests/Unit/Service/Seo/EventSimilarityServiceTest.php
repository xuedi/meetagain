<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Service\Seo\EventSimilarityService;
use PHPUnit\Framework\TestCase;

class EventSimilarityServiceTest extends TestCase
{
    private function makeEvent(string $title, string $teaser, string $description, string $locale = 'en'): Event
    {
        $translation = new EventTranslation();
        $translation->setLanguage($locale);
        $translation->setTitle($title);
        $translation->setTeaser($teaser);
        $translation->setDescription($description);

        $event = new Event();
        $event->addTranslation($translation);

        return $event;
    }

    public function testIdenticalContentScoresZero(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $left = $this->makeEvent('Board game night', 'Bring your own dice', 'We meet every week to play board games.');
        $right = $this->makeEvent('Board game night', 'Bring your own dice', 'We meet every week to play board games.');

        // Act
        $score = $service->compare($left, $right, 'en');

        // Assert
        static::assertSame(0.0, $score->total);
    }

    public function testCompletelyDifferentContentScoresNearOneHundred(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $left = $this->makeEvent('Board game night', 'Bring dice', 'We meet every week to play board games.');
        $right = $this->makeEvent('Ölwechsel Werkstatt', 'Kfz-Zubehör', 'Wir zerlegen Motoren und tauschen Zündkerzen.');

        // Act
        $score = $service->compare($left, $right, 'en');

        // Assert
        static::assertGreaterThan(70.0, $score->total);
    }

    public function testDescriptionChangeOutweighsTitleChangeOfTheSameMagnitude(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $base = $this->makeEvent('Weekly meetup', 'Come along', 'Same text on both sides here.');
        $titleChanged = $this->makeEvent('Completely other heading', 'Come along', 'Same text on both sides here.');
        $descriptionChanged = $this->makeEvent('Weekly meetup', 'Come along', 'Completely other body content here.');

        // Act
        $titleScore = $service->compare($base, $titleChanged, 'en');
        $descriptionScore = $service->compare($base, $descriptionChanged, 'en');

        // Assert
        static::assertGreaterThan($titleScore->total, $descriptionScore->total);
    }

    public function testMarkupAndWhitespaceDifferencesAloneScoreZero(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $left = $this->makeEvent('Quiz night', '', '<p>Join us for a   quiz.</p>');
        $right = $this->makeEvent('Quiz night', '', "<div><strong>Join</strong> us for a quiz.</div>\n");

        // Act
        $score = $service->compare($left, $right, 'en');

        // Assert
        static::assertSame(0.0, $score->total);
    }

    public function testFieldsEmptyOnBothSidesDoNotDistortTheWeighting(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $withTeaser = $this->makeEvent('Same title', 'A teaser', 'Fully rewritten body text alpha.');
        $withoutTeaser = $this->makeEvent('Same title', '', 'Fully rewritten body text alpha.');

        $noTeaserLeft = $this->makeEvent('Same title', '', 'One body text here.');
        $noTeaserRight = $this->makeEvent('Same title', '', 'Utterly unrelated wording.');

        // Act
        $emptyOnOneSide = $service->compare($withTeaser, $withoutTeaser, 'en');
        $emptyOnBothSides = $service->compare($noTeaserLeft, $noTeaserRight, 'en');

        // Assert
        static::assertSame(20.0, $emptyOnOneSide->total);
        static::assertSame(0.0, $emptyOnBothSides->teaser);
        // teaser drops out of the weighting entirely: only title (20) and description (60) remain
        static::assertSame(round($emptyOnBothSides->description * 60 / 80, 2), $emptyOnBothSides->total);
    }

    public function testMissingTranslationCountsAsFullyChanged(): void
    {
        // Arrange
        $service = new EventSimilarityService();
        $translated = $this->makeEvent('Title', 'Teaser', 'Description');
        $untranslated = new Event();

        // Act
        $score = $service->compare($translated, $untranslated, 'en');

        // Assert
        static::assertSame(100.0, $score->total);
    }
}
