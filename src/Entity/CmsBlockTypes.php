<?php declare(strict_types=1);

namespace App\Entity;

use App\Entity\BlockType\BlockType;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Image;
use App\Entity\BlockType\Paragraph;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\Title;
use App\Entity\Image as ImageEntity;
use RuntimeException;

enum CmsBlockTypes: int
{
    case Headline = 1;
    case Text = 2;
    case Image = 3;
    case Video = 4;
    case Paragraph = 5;
    case Events = 6;
    case Gallery = 7;
    case Hero = 8;
    case EventTeaser = 9;
    case Title = 10;

    public static function buildObject(self $type, array $data, null|ImageEntity $image = null): BlockType
    {
        return match ($type) {
            CmsBlockTypes::Headline => Headline::fromJson($data, $image),
            CmsBlockTypes::Text => Text::fromJson($data, $image),
            CmsBlockTypes::Image => Image::fromJson($data, $image),
            CmsBlockTypes::Hero => Hero::fromJson($data, $image),
            CmsBlockTypes::Paragraph => Paragraph::fromJson($data, $image),
            CmsBlockTypes::Title => Title::fromJson($data, $image),
            CmsBlockTypes::EventTeaser => EventTeaser::fromJson($data, $image),
            default => throw new RuntimeException('Unknown block type: ' . $type->name),
        };
    }
}
