<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\ChangeField;
use App\Item\Taxonomy\ChangeFieldCodec;
use App\Item\Taxonomy\ChangeOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChangeFieldCodecTest extends TestCase
{
    /** @return iterable<string, array{ChangeField, string}> */
    public static function fieldProvider(): iterable
    {
        yield 'add a category' => [new ChangeField(Axis::Category, ChangeOperation::Add, locale: 'en', index: 2), 'category_add_en_2'];
        yield 'rename a category' => [new ChangeField(Axis::Category, ChangeOperation::Rename, id: 3, locale: 'de'), 'category_rename_3_de'];
        yield 'remove a category' => [new ChangeField(Axis::Category, ChangeOperation::Remove, id: 3), 'category_remove_3'];
        yield 'add a tag' => [new ChangeField(Axis::Tag, ChangeOperation::Add, locale: 'zh', index: 0), 'tag_add_zh_0'];
        yield 'rename a tag' => [new ChangeField(Axis::Tag, ChangeOperation::Rename, id: 9, locale: 'en'), 'tag_rename_9_en'];
        yield 'remove a tag' => [new ChangeField(Axis::Tag, ChangeOperation::Remove, id: 9), 'tag_remove_9'];
    }

    #[DataProvider('fieldProvider')]
    public function testKeyRoundTripsThroughTheCodec(ChangeField $field, string $key): void
    {
        // Arrange
        $codec = new ChangeFieldCodec();

        // Act
        $parsed = $codec->parse($field->key());

        // Assert
        static::assertSame($key, $field->key());
        static::assertNotNull($parsed);
        static::assertSame($field->axis, $parsed->axis);
        static::assertSame($field->operation, $parsed->operation);
        static::assertSame($field->id, $parsed->id);
        static::assertSame($field->locale, $parsed->locale);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFieldProvider(): iterable
    {
        yield 'unknown axis' => ['flavour_remove_1'];
        yield 'unknown operation' => ['category_merge_1'];
        yield 'missing locale' => ['category_rename_1'];
        yield 'non-numeric id' => ['category_remove_x'];
        yield 'a foreign field key' => ['explanation'];
    }

    #[DataProvider('invalidFieldProvider')]
    public function testAnUnparsableKeyIsRejected(string $field): void
    {
        // Arrange
        $codec = new ChangeFieldCodec();

        // Act + Assert
        static::assertNull($codec->parse($field));
    }

    public function testLabelKeyNamesTheAxisAndOperation(): void
    {
        // Arrange + Act
        $field = new ChangeField(Axis::Tag, ChangeOperation::Remove, id: 1);

        // Assert
        static::assertSame('item.taxonomy_field_tag_remove', $field->labelKey());
    }
}
