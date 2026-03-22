<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\FieldDefinition;
use App\Enum\CmsBlock\FieldType;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase
{
    // --- Arrange / Act / Assert ---

    public function testRequiredFieldHasNoDefault(): void
    {
        // Arrange / Act
        $field = new FieldDefinition('title', FieldType::String);

        // Assert
        static::assertSame('title', $field->name);
        static::assertSame(FieldType::String, $field->type);
        static::assertTrue($field->required);
        static::assertNull($field->default);
    }

    public function testOptionalFieldWithDefault(): void
    {
        // Arrange / Act
        $field = new FieldDefinition('imageRight', FieldType::Boolean, required: false, default: false);

        // Assert
        static::assertFalse($field->required);
        static::assertFalse($field->default);
    }

    public function testOptionalFieldWithStringDefault(): void
    {
        // Arrange / Act
        $field = new FieldDefinition('color', FieldType::Color, required: false, default: '#f14668');

        // Assert
        static::assertSame('#f14668', $field->default);
    }

    public function testOptionalFieldWithArrayDefault(): void
    {
        // Arrange / Act
        $field = new FieldDefinition('images', FieldType::ImageList, required: false, default: []);

        // Assert
        static::assertSame([], $field->default);
    }
}
