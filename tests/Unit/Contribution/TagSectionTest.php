<?php declare(strict_types=1);

namespace Tests\Unit\Contribution;

use App\Contribution\TagSection;
use App\Entity\User;
use App\Item\Tag\TypeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class TagSectionTest extends TestCase
{
    public function testATypeWhosePluginIsOffIsNotOffered(): void
    {
        // Arrange
        $section = $this->section([]);

        // Act & Assert
        self::assertFalse($section->mayTouch(new User(), 'film'), 'the tag registry already drops a type whose plugin is off');
    }

    public function testAnActiveTypeIsTouchable(): void
    {
        // Arrange
        $section = $this->section(['film' => 'item.type_films']);

        // Act & Assert
        self::assertTrue($section->mayTouch(new User(), 'film'));
    }

    public function testAStewardIsSentToTheirOwnEditorInstead(): void
    {
        // Arrange
        $section = $this->section(['film' => 'item.type_films'], isSteward: true);

        // Act & Assert
        self::assertFalse($section->mayTouch(new User(), 'film'));
    }

    /**
     * @param array<string, string> $types type key => label key
     */
    private function section(array $types, bool $isSteward = false): TagSection
    {
        $registry = $this->createStub(TypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn(string $typeKey): bool => isset($types[$typeKey]));

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isSteward);

        return new TagSection($registry, $security);
    }
}
