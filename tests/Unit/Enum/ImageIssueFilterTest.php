<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\ImageIssueFilter;
use PHPUnit\Framework\TestCase;

class ImageIssueFilterTest extends TestCase
{
    public function testLabelFollowsTheTranslationKeyConvention(): void
    {
        // Act & Assert
        foreach (ImageIssueFilter::cases() as $case) {
            static::assertSame('admin_system_images.issues_filter_' . $case->value, $case->label());
        }
    }

    public function testValuesRoundTripThroughTryFrom(): void
    {
        // Act & Assert
        static::assertSame(ImageIssueFilter::MissingAlt, ImageIssueFilter::tryFrom('missing_alt'));
        static::assertNull(ImageIssueFilter::tryFrom('bogus'));
    }
}
