<?php declare(strict_types=1);

namespace Tests\Unit\Item;

use App\Item\AttachControlBuilder;
use App\Item\TypeProviderInterface;
use App\Item\TypeRegistry;
use PHPUnit\Framework\TestCase;

class AttachControlBuilderTest extends TestCase
{
    public function testBuildCarriesTheProvidersAttachHeadingAndHelpKeys(): void
    {
        // Arrange
        $provider = $this->createStub(TypeProviderInterface::class);
        $provider->method('getKey')->willReturn('book');
        $provider->method('getLabelKey')->willReturn('books.item_label');
        $provider->method('getAttachHeadingKey')->willReturn('books.attach_heading');
        $provider->method('getAttachHelpKey')->willReturn('books.attach_help');
        $provider->method('renderAttachPicker')->willReturn('<picker>');
        $registry = $this->createStub(TypeRegistry::class);
        $registry->method('all')->willReturn(['book' => $provider]);
        $subject = new AttachControlBuilder($registry, []);

        // Act
        $control = $subject->build(7);

        // Assert
        $type = $control->getTypes()[0];
        $this->assertSame('books.attach_heading', $type->getAttachHeadingKey());
        $this->assertSame('books.attach_help', $type->getAttachHelpKey());
        $this->assertSame('<picker>', $type->getPickerHtml());
    }
}
