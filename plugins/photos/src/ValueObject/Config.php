<?php declare(strict_types=1);

namespace Plugin\Photos\ValueObject;

use App\Publisher\PluginSettings\Data;

final class Config implements Data
{
    private bool $memberUploads = true;

    private bool $showCameraMeta = true;

    public function isMemberUploads(): bool
    {
        return $this->memberUploads;
    }

    public function setMemberUploads(bool $memberUploads): static
    {
        $this->memberUploads = $memberUploads;

        return $this;
    }

    public function isShowCameraMeta(): bool
    {
        return $this->showCameraMeta;
    }

    public function setShowCameraMeta(bool $showCameraMeta): static
    {
        $this->showCameraMeta = $showCameraMeta;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'memberUploads' => $this->memberUploads,
            'showCameraMeta' => $this->showCameraMeta,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->setMemberUploads((bool) ($raw['memberUploads'] ?? true));
        $config->setShowCameraMeta((bool) ($raw['showCameraMeta'] ?? true));

        return $config;
    }
}
