<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\BlockType\Text;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Headline;
use App\Enum\CmsBlock\CmsBlockType;
use App\Exception\BlockValidationException;
use App\Service\Cms\BlockHydrator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class BlockHydratorTest extends TestCase
{
    private BlockHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new BlockHydrator($this->makeSanitizer());
    }

    private function makeSanitizer(string $prefix = ''): HtmlSanitizerInterface
    {
        return new class ($prefix) implements HtmlSanitizerInterface {
            public function __construct(private readonly string $prefix) {}
            public function sanitize(string $input): string { return $this->prefix . $input; }
            public function sanitizeFor(string $context, string $input): string { return $this->prefix . $input; }
        };
    }

    // --- Arrange / Act / Assert ---

    public function testHydratesValidPayload(): void
    {
        // Arrange
        $payload = ['content' => 'Hello world'];

        // Act
        $result = $this->hydrator->hydrate(CmsBlockType::Text, $payload);

        // Assert
        static::assertInstanceOf(Text::class, $result);
        static::assertSame('Hello world', $result->content);
    }

    public function testAppliesDefaultForOptionalMissingField(): void
    {
        // Arrange
        $payload = ['content' => 'Some text']; // imageRight omitted

        // Act
        $result = $this->hydrator->hydrate(CmsBlockType::Text, $payload);

        // Assert
        static::assertFalse($result->imageRight);
    }

    public function testCoercesBooleanField(): void
    {
        // Arrange
        $payload = ['content' => 'Some text', 'imageRight' => '1'];

        // Act
        $result = $this->hydrator->hydrate(CmsBlockType::Text, $payload);

        // Assert
        static::assertTrue($result->imageRight);
    }

    public function testThrowsOnMissingRequiredField(): void
    {
        // Arrange
        $payload = []; // missing required 'title'

        // Assert
        $this->expectException(BlockValidationException::class);
        $this->expectExceptionMessage('Missing required field "title"');

        // Act
        $this->hydrator->hydrate(CmsBlockType::Headline, $payload);
    }

    public function testThrowsWithAllMissingRequiredFields(): void
    {
        // Arrange
        $payload = []; // headline, subHeadline, text, buttonLink, buttonText all required

        // Act
        try {
            $this->hydrator->hydrate(CmsBlockType::Hero, $payload);
            static::fail('Expected BlockValidationException');
        } catch (BlockValidationException $e) {
            // Assert
            static::assertCount(5, $e->errors);
        }
    }

    public function testAppliesDefaultColorForHero(): void
    {
        // Arrange
        $payload = [
            'headline'    => 'H',
            'subHeadline' => 'S',
            'text'        => 'T',
            'buttonLink'  => '/link',
            'buttonText'  => 'Click',
        ];

        // Act
        $result = $this->hydrator->hydrate(CmsBlockType::Hero, $payload);

        // Assert
        static::assertInstanceOf(Hero::class, $result);
        static::assertSame('#f14668', $result->color);
    }

    public function testHydratesHeadlineBlock(): void
    {
        // Arrange
        $payload = ['title' => 'My Title'];

        // Act
        $result = $this->hydrator->hydrate(CmsBlockType::Headline, $payload);

        // Assert
        static::assertInstanceOf(Headline::class, $result);
        static::assertSame('My Title', $result->title);
    }

    public function testSanitizesRichTextFields(): void
    {
        // Arrange
        $hydrator = new BlockHydrator($this->makeSanitizer('SANITIZED:'));
        $payload = ['content' => '<p>Body</p>'];

        // Act
        $result = $hydrator->hydrate(CmsBlockType::Text, $payload);

        // Assert
        static::assertSame('SANITIZED:<p>Body</p>', $result->content);
    }

    public function testDoesNotSanitizePlainFields(): void
    {
        // Arrange
        $hydrator = new BlockHydrator($this->makeSanitizer('SANITIZED:'));
        $payload = ['title' => 'Plain Title', 'content' => 'Body'];

        // Act
        $result = $hydrator->hydrate(CmsBlockType::Text, $payload);

        // Assert
        static::assertSame('Plain Title', $result->title);
    }
}
