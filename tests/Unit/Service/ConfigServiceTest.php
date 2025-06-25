<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\ImageType;
use App\Service\ConfigService;
use PHPUnit\Framework\TestCase;

class ConfigServiceTest extends TestCase
{
    private ConfigService $subject;

    protected function setUp(): void
    {
        $this->subject = new ConfigService();
    }

    public function testGetThumbnailSizesForProfilePicture(): void
    {
        $sizes = $this->subject->getThumbnailSizes(ImageType::ProfilePicture);
        $expected = [
            [400, 400],
            [80, 80],
            [50, 50]
        ];

        $this->assertSame($expected, $sizes);
    }

    public function testGetThumbnailSizesForEventTeaser(): void
    {
        $sizes = $this->subject->getThumbnailSizes(ImageType::EventTeaser);
        $expected = [
            [1024, 768],
            [600, 400],
            [210, 140]
        ];

        $this->assertSame($expected, $sizes);
    }

    public function testGetThumbnailSizesForEventUpload(): void
    {
        $sizes = $this->subject->getThumbnailSizes(ImageType::EventUpload);
        $expected = [
            [1024, 768],
            [210, 140]
        ];

        $this->assertSame($expected, $sizes);
    }

    public function testGetThumbnailSizesForCmsBlock(): void
    {
        $sizes = $this->subject->getThumbnailSizes(ImageType::CmsBlock);
        $expected = [
            [432, 432],
            [80, 80],
        ];

        $this->assertSame($expected, $sizes);
    }
}
