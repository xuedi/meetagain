<?php declare(strict_types=1);

namespace Plugin\Photos\ValueObject;

use App\Publisher\PluginSettings\Data;

final class Config implements Data
{
    private bool $memberUploads = true;

    private bool $showCameraMeta = true;

    private bool $memberStreams = true;

    private bool $eventBox = true;

    private bool $contest = false;

    private int $contestSubmissionsPerMember = 1;

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

    public function isMemberStreams(): bool
    {
        return $this->memberStreams;
    }

    public function setMemberStreams(bool $memberStreams): static
    {
        $this->memberStreams = $memberStreams;

        return $this;
    }

    public function isEventBox(): bool
    {
        return $this->eventBox;
    }

    public function setEventBox(bool $eventBox): static
    {
        $this->eventBox = $eventBox;

        return $this;
    }

    public function isContest(): bool
    {
        return $this->contest;
    }

    public function setContest(bool $contest): static
    {
        $this->contest = $contest;

        return $this;
    }

    public function getContestSubmissionsPerMember(): int
    {
        return $this->contestSubmissionsPerMember;
    }

    public function setContestSubmissionsPerMember(int $contestSubmissionsPerMember): static
    {
        $this->contestSubmissionsPerMember = max(1, $contestSubmissionsPerMember);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'memberUploads' => $this->memberUploads,
            'showCameraMeta' => $this->showCameraMeta,
            'memberStreams' => $this->memberStreams,
            'eventBox' => $this->eventBox,
            'contest' => $this->contest,
            'contestSubmissionsPerMember' => $this->contestSubmissionsPerMember,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->setMemberUploads((bool) ($raw['memberUploads'] ?? true));
        $config->setShowCameraMeta((bool) ($raw['showCameraMeta'] ?? true));
        $config->setMemberStreams((bool) ($raw['memberStreams'] ?? true));
        $config->setEventBox((bool) ($raw['eventBox'] ?? true));
        $config->setContest((bool) ($raw['contest'] ?? false));
        $config->setContestSubmissionsPerMember((int) ($raw['contestSubmissionsPerMember'] ?? 1));

        return $config;
    }
}
