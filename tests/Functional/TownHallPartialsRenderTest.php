<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\Topic;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class TownHallPartialsRenderTest extends KernelTestCase
{
    public static function activePageProvider(): iterable
    {
        yield 'the dashboard button is selected on the dashboard' => ['dashboard', '/en/townhall'];
        yield 'the gallery button is selected on the gallery' => ['gallery', '/en/townhall/gallery'];
    }

    #[DataProvider('activePageProvider')]
    public function testTheHeaderMarksOnlyTheActivePage(string $active, string $expectedHref): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_header.html.twig', ['active' => $active]);

        // Assert
        self::assertSame(1, substr_count($html, 'is-link is-selected'));
        self::assertMatchesRegularExpression('~href="' . preg_quote($expectedHref, '~') . '"[^>]*class="button is-link is-selected"|class="button is-link is-selected"[^>]*href="' . preg_quote($expectedHref, '~') . '"~', $html);
    }

    public function testTheHeaderCarriesAllThreePages(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_header.html.twig', ['active' => 'dashboard']);

        // Assert
        self::assertStringContainsString('Dashboard', $html);
        self::assertStringContainsString('Forum', $html);
        self::assertStringContainsString('Gallery', $html);
    }

    public function testTheStatsStripCountsMembersEventsAndTopics(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_stats_strip.html.twig', [
            'stats' => ['memberCount' => 12, 'eventCount' => 3, 'topicCount' => 7],
        ]);

        // Assert
        self::assertSame(3, substr_count($html, 'level-item has-text-centered'));
        foreach (['12', '3', '7'] as $count) {
            self::assertStringContainsString('<p class="title is-4">' . $count . '</p>', $html);
        }
    }

    public function testAConversationRowLabelsTheTopicItHappenedIn(): void
    {
        // Arrange
        $twig = $this->twig();
        $root = $this->topic(1, 'Language');
        $child = $this->topic(2, 'learn materials', $root);

        // Act
        $html = $twig->render('town_hall/_tile_conversations.html.twig', [
            'conversations' => [[
                'comment' => $this->comment('The Dreyer workbook is the best one.'),
                'event' => null,
                'topic' => $child,
                'topicPath' => [$root, $child],
            ]],
        ]);

        // Assert
        self::assertStringContainsString('The Dreyer workbook is the best one.', $html);
        self::assertStringContainsString('Language', $html);
        self::assertStringContainsString('learn materials', $html);
    }

    public function testAnEmptyFeedShowsTheEmptyState(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_tile_conversations.html.twig', ['conversations' => []]);

        // Assert
        self::assertStringContainsString('Nothing here yet.', $html);
    }

    public function testTheTreeOffersASubtopicLinkOnlyWhileBelowTheDepthCap(): void
    {
        // Arrange
        $twig = $this->twig();
        $root = $this->topic(1, 'Language');
        $child = $this->topic(2, 'learn materials', $root);
        $leaf = $this->topic(3, 'podcasts', $child);

        // Act
        $html = $twig->render('town_hall/_topic_tree.html.twig', [
            'childrenByParent' => [0 => [$root], 1 => [$child], 2 => [$leaf]],
            'commentCounts' => [],
            'activeId' => null,
            'parentId' => 0,
            'depth' => 1,
            'maxDepth' => Topic::MAX_DEPTH,
        ]);

        // Assert
        self::assertStringContainsString('parent=1', $html);
        self::assertStringContainsString('parent=2', $html);
        self::assertStringNotContainsString('parent=3', $html);
    }

    public function testTheTreeTagsCommentCountsAndMarksTheActiveNode(): void
    {
        // Arrange
        $twig = $this->twig();
        $root = $this->topic(1, 'Language');
        $sibling = $this->topic(4, 'Help');

        // Act
        $html = $twig->render('town_hall/_topic_tree.html.twig', [
            'childrenByParent' => [0 => [$root, $sibling]],
            'commentCounts' => [1 => 3],
            'activeId' => 4,
            'parentId' => 0,
            'depth' => 1,
            'maxDepth' => Topic::MAX_DEPTH,
        ]);

        // Assert
        self::assertStringContainsString('<span class="tag is-light is-rounded">3</span>', $html);
        self::assertSame(1, substr_count($html, 'has-text-weight-bold'));
        self::assertMatchesRegularExpression('~href="[^"]*/4"[^>]*has-text-weight-bold~', $html);
    }

    public function testTheGalleryBindsEachUploadToTheSharedStage(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_gallery_stage.html.twig', ['images' => [$this->eventImage('deadbeef', 11)]]);

        // Assert
        self::assertStringContainsString('data-item-gallery-stage', $html);
        self::assertStringContainsString('href="/images/thumbnails/deadbeef_1024x768.webp"', $html);
        self::assertStringContainsString('src="/images/thumbnails/deadbeef_350x263.webp"', $html);
        self::assertStringContainsString('data-item-gallery-url="/en/event/11"', $html);
    }

    public function testAnEmptyGalleryRendersNoStage(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $html = $twig->render('town_hall/_gallery_stage.html.twig', ['images' => []]);

        // Assert
        self::assertStringNotContainsString('data-item-gallery-stage', $html);
        self::assertStringContainsString('No images to show here yet.', $html);
    }

    private function eventImage(string $hash, int $eventId): Image
    {
        $event = new Event();
        new ReflectionProperty(Event::class, 'id')->setValue($event, $eventId);

        $image = new Image();
        $image->setHash($hash);
        $image->setEvent($event);
        $image->setCreatedAt(new DateTimeImmutable('2026-07-01 10:00:00'));

        return $image;
    }

    private function topic(int $id, string $title, ?Topic $parent = null): Topic
    {
        $topic = new Topic()->setTitle($title)->setParent($parent);
        new ReflectionProperty(Topic::class, 'id')->setValue($topic, $id);

        return $topic;
    }

    private function comment(string $content): Comment
    {
        $comment = new Comment();
        $comment->setContent($content);
        $comment->setCreatedAt(new DateTimeImmutable('2026-08-01 12:00:00'));
        $comment->setUser(new User()->setName('Lana Steiner'));

        return $comment;
    }

    private function twig(): Environment
    {
        self::bootKernel();
        $request = Request::create('/en/townhall');
        $request->setLocale('en');
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
