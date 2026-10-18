<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\PluginActivationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PluginActivationRepository::class)]
class PluginActivation
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'plugin_key', length: 64)]
        private string $pluginKey,
        #[ORM\Column]
        private bool $enabled,
        #[ORM\Column]
        private DateTimeImmutable $changedAt,
    ) {}

    public function getPluginKey(): string
    {
        return $this->pluginKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getChangedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function switchTo(bool $enabled, DateTimeImmutable $changedAt): void
    {
        $this->enabled = $enabled;
        $this->changedAt = $changedAt;
    }
}
