<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageAbstract;

class AdminEventCancelled extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::AdminEventCancelled;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');

        return $this;
    }

    protected function renderText(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? '[deleted]';

        return sprintf('Cancelled event: %s', $eventName);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
