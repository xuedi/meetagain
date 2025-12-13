<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class ChangedUsername extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::ChangedUsername;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('old');
        $this->ensureHasKey('new');

        return $this;
    }

    protected function renderText(): string
    {
        $msgTemplate = 'Changed username from %s to %s';
        return sprintf($msgTemplate, $this->meta['old'], $this->meta['new']);
    }

    protected function renderHtml(): string
    {
        $msgTemplate = 'Changed username from <b>%s</b> to <b>%s</b>';
        return sprintf($msgTemplate, $this->meta['old'], $this->meta['new']);
    }
}
