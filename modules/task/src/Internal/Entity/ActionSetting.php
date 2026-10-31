<?php declare(strict_types=1);

namespace Module\Task\Internal\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Task\Internal\Repository\ActionSettingRepository;

#[ORM\Entity(repositoryClass: ActionSettingRepository::class)]
#[ORM\Table(name: 'mod_task_action_setting')]
#[ORM\UniqueConstraint(name: 'uniq_task_action_setting', columns: ['identifier', 'setting_key'])]
class ActionSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $identifier,
        #[ORM\Column(name: 'setting_key', length: 64)]
        private string $key,
        #[ORM\Column(name: 'setting_value', type: Types::JSON, nullable: true)]
        private string|int|bool|null $value,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string|int|bool|null
    {
        return $this->value;
    }

    public function change(string|int|bool|null $value): void
    {
        $this->value = $value;
    }
}
