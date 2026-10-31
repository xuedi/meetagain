<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class Translation
{
    public const int MAX_NAME_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(length: self::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\Column(length: self::MAX_NAME_LENGTH)]
    private string $nameSlug;

    public function __construct(string $language, string $name, string $nameSlug)
    {
        $this->language = $language;
        $this->name = $name;
        $this->nameSlug = $nameSlug;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameSlug(): string
    {
        return $this->nameSlug;
    }

    public function rename(string $name, string $nameSlug): void
    {
        $this->name = $name;
        $this->nameSlug = $nameSlug;
    }
}
