<?php declare(strict_types=1);

namespace App\Enum;

use App\Entity\BlockType\BlockCapabilities;
use App\Entity\BlockType\BlockType;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\Gallery;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Paragraph;
use App\Entity\BlockType\Text;
use App\Entity\Image as ImageEntity;
use RuntimeException;

enum CmsBlockType: int
{
    case Headline = 1;
    case Text = 2;
    case Video = 4; // TODO: to me implemented
    case Paragraph = 5;
    case Events = 6; // TODO: to me implemented
    case Gallery = 7;
    case Hero = 8;
    case EventTeaser = 9;

    public static function buildObject(self $type, array $data, ?ImageEntity $image = null): BlockType
    {
        return match ($type) {
            CmsBlockType::Headline => Headline::fromJson($data, $image),
            CmsBlockType::Text => Text::fromJson($data, $image),
            CmsBlockType::Gallery => Gallery::fromJson($data, $image),
            CmsBlockType::Hero => Hero::fromJson($data, $image),
            CmsBlockType::Paragraph => Paragraph::fromJson($data, $image),
            CmsBlockType::EventTeaser => EventTeaser::fromJson($data, $image),
        };
    }

    public function getCapabilities(): BlockCapabilities
    {
        return match ($this) {
            self::Headline => Headline::getCapabilities(),
            self::Text => Text::getCapabilities(),
            self::Paragraph => Paragraph::getCapabilities(),
            self::Gallery => Gallery::getCapabilities(),
            self::Hero => Hero::getCapabilities(),
            self::EventTeaser => EventTeaser::getCapabilities(),
        };
    }
}
