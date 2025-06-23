<?php declare(strict_types=1);

namespace App\Entity\Activity\Messages;

use App\Entity\Activity\MessageAbstract;
use App\Entity\ActivityType;

class ChangedUsername extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::ChangedUsername;
    }

    public function validate(): void
    {
        $this->ensureHasKey('old');
        $this->ensureHasKey('new');
    }

    protected function renderText(): string
    {
        $msgTemplate = 'Changed username from %s to %s';
        return sprintf(
            $msgTemplate,
            $this->meta['old'],
            $this->meta['new']
        );
    }

    protected function renderHtml(): string
    {
        $msgTemplate = 'Changed username from <b>%s</b> to <b>%s</b>';
        return sprintf(
            $msgTemplate,
            $this->meta['old'],
            $this->meta['new']
        );
    }
}
