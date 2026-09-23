<?php declare(strict_types=1);

namespace Plugin\Karaoke\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'plg_karaoke_line_translation')]
#[ORM\UniqueConstraint(name: 'uniq_karaoke_translation_lang_line', columns: ['language', 'line_id'])]
class LineTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LyricLine $line = null;

    #[ORM\Column(length: 2)]
    private ?string $language = null;

    #[ORM\Column(length: 500)]
    private string $text = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLine(): ?LyricLine
    {
        return $this->line;
    }

    public function setLine(?LyricLine $line): static
    {
        $this->line = $line;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }
}
