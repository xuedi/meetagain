<?php declare(strict_types=1);

namespace App\Enum\CmsBlock;

use App\Entity\BlockType\BlockCapabilities;
use App\Entity\BlockType\BlockType;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\FieldDefinition;
use App\Entity\BlockType\Gallery;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\TrioCards;
use LogicException;

enum CmsBlockType: int
{
    case Headline = 1;
    case Text = 2;
    case Video = 4; // TODO: to be implemented
    case Events = 6; // TODO: to be implemented
    case Gallery = 7;
    case Hero = 8;
    case EventTeaser = 9;
    case TrioCards = 10;

    /** @return class-string<BlockType> */
    public function getBlockClass(): string
    {
        return match ($this) {
            self::Headline    => Headline::class,
            self::Text        => Text::class,
            self::Gallery     => Gallery::class,
            self::Hero        => Hero::class,
            self::EventTeaser => EventTeaser::class,
            self::TrioCards   => TrioCards::class,
            default           => throw new LogicException(sprintf('CmsBlockType::%s is not yet implemented.', $this->name)),
        };
    }

    public function getCapabilities(): BlockCapabilities
    {
        return $this->getBlockClass()::getCapabilities();
    }

    /** @return list<FieldDefinition> */
    public function getFieldDefinitions(): array
    {
        return $this->getBlockClass()::getFieldDefinitions();
    }
}
