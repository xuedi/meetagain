<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class RsvpYes extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::RsvpYes;
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
        $msgTemplate = 'Going to event: %s';
        return sprintf($msgTemplate, $this->eventNames[$eventId]);
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $msgTemplate = 'Going to event: %s'; // TODO: link
        return sprintf($msgTemplate, $this->eventNames[$eventId]);
    }
}
