<?php declare(strict_types=1);

namespace App\Tests\Unit\Admin\Top;

use App\Admin\Top\Infos\AdminTopInfoText;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Admin\Top\Infos\AdminTopInfoText
 */
final class AdminTopInfoTextTest extends TestCase
{
    public function testTextConstructorStoresPlainTextVerbatim(): void
    {
        // Arrange
        $value = 'hello';

        // Act
        $info = new AdminTopInfoText($value);

        // Assert
        static::assertSame('hello', $info->text);
    }

    public function testGetTemplateReturnsExpectedPartialPath(): void
    {
        // Arrange
        $info = new AdminTopInfoText('any');

        // Act
        $template = $info->getTemplate();

        // Assert
        static::assertSame('admin/_components/admin_top/_info_text.html.twig', $template);
    }
}
