<?php declare(strict_types=1);

namespace App\Entity\Activity\Messages;

use App\Entity\Activity\MessageAbstract;
use App\Entity\ActivityType;

class RsvpYes extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::RsvpYes;
    }

    public function validate(): void
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');
    }

    protected function renderText(): string
    {
        $eventId = $this->meta['event_id'];
        $msgTemplate = 'Going to event: %s';
        return sprintf(
            $msgTemplate,
            $this->eventNames[$eventId],
        );
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $msgTemplate = 'Going to event: %s'; // TODO: link
        return sprintf(
            $msgTemplate,
            $this->eventNames[$eventId],
        );
    }
}
