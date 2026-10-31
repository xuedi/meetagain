<?php declare(strict_types=1);

namespace Module\Geo\Internal\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mod_geo_state_translation')]
#[ORM\UniqueConstraint(name: 'uniq_geo_state_translation', columns: ['state_id', 'language'])]
#[ORM\Index(name: 'idx_geo_state_translation_name', columns: ['language', 'name'])]
#[ORM\Index(name: 'idx_geo_state_translation_slug', columns: ['name_slug'])]
class StateTranslation extends Translation
{
    #[ORM\ManyToOne(targetEntity: State::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'state_id', nullable: false, onDelete: 'CASCADE')]
    private State $state;

    public function __construct(State $state, string $language, string $name, string $nameSlug)
    {
        parent::__construct($language, $name, $nameSlug);
        $this->state = $state;
    }

    public function getState(): State
    {
        return $this->state;
    }
}
