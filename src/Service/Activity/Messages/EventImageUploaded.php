<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class EventImageUploaded extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::EventImageUploaded;
    }

    public function validate(): void
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');
        $this->ensureHasKey('images');
        $this->ensureIsNumeric('images');
    }

    protected function renderText(): string
    {
        $eventId = $this->meta['event_id'];
        $msgTemplate = 'uploaded %d images to the event %s';
        return sprintf(
            $msgTemplate,
            $this->meta['images'],
            $this->eventNames[$eventId]
        );
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $msgTemplate = 'uploaded <b>%d</b> images to the event <a href="%s">%s</a>';
        return sprintf(
            $msgTemplate,
            $this->meta['images'],
            $this->router->generate('app_event_details', ['id' => $eventId]),
            $this->eventNames[$eventId]
        );
    }
}
