<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageAbstract;

class AdminEventDeleted extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::AdminEventDeleted;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');
        $this->ensureHasKey('event_name');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Deleted event: %s', $this->meta['event_name']);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
