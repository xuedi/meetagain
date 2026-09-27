<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Contract;

use Module\Email\Contract\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    public function testAStoredRowReadsBackAsTheSameAttachment(): void
    {
        // Arrange
        $attachment = new Attachment('/var/invoices/1.pdf', 'INV-1.pdf');

        // Act
        $restored = Attachment::fromArray($attachment->toArray());

        // Assert
        static::assertEquals($attachment, $restored);
    }

    /**
     * @param array{path?: mixed, filename?: mixed} $row
     */
    #[DataProvider('provideUnusableRows')]
    public function testAnUnusableRowReadsBackAsNothing(array $row): void
    {
        // Act
        $restored = Attachment::fromArray($row);

        // Assert
        static::assertNull($restored);
    }

    /**
     * @return iterable<string, array{array{path?: mixed, filename?: mixed}}>
     */
    public static function provideUnusableRows(): iterable
    {
        yield 'no path' => [['filename' => 'INV-1.pdf']];
        yield 'no filename' => [['path' => '/var/invoices/1.pdf']];
        yield 'an empty path' => [['path' => '', 'filename' => 'INV-1.pdf']];
        yield 'a path that is not a string' => [['path' => 7, 'filename' => 'INV-1.pdf']];
    }
}
