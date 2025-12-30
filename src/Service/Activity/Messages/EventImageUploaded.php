<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class EventImageUploaded extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::EventImageUploaded;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');
        $this->ensureHasKey('images');
        $this->ensureIsNumeric('images');

        return $this;
    }

    protected function renderText(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? '[deleted]';
        return sprintf('uploaded %d images to the event %s', $this->meta['images'], $eventName);
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? '[deleted]';
        if ($eventName === '[deleted]') {
            return sprintf('uploaded <b>%d</b> images to event [deleted]', $this->meta['images']);
        }
        return sprintf(
            'uploaded <b>%d</b> images to the event <a href="%s">%s</a>',
            $this->meta['images'],
            $this->router->generate('app_event_details', ['id' => $eventId]),
            $eventName,
        );
    }
}
