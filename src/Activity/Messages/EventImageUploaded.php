<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class EventImageUploaded extends MessageAbstract
{
    public const string TYPE = 'core.event_image_uploaded';

    public function getType(): string
    {
        return self::TYPE;
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
        $eventName = $this->eventNames[$eventId] ?? null;
        if ($eventName === null) {
            return $this->translator->trans('profile_social.activity_event_images_uploaded_deleted', ['%count%' => $this->meta['images']]);
        }

        return $this->translator->trans('profile_social.activity_event_images_uploaded', ['%event%' => $eventName, '%count%' => $this->meta['images']]);
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? null;
        if ($eventName === null) {
            return $this->translator->trans('profile_social.activity_event_images_uploaded_deleted', ['%count%' => $this->meta['images']]);
        }

        $link = sprintf('<a href="%s">%s</a>', $this->router->generate('app_event_details', ['id' => $eventId]), $this->escapeHtml($eventName));

        return $this->translator->trans('profile_social.activity_event_images_uploaded', ['%event%' => $link, '%count%' => $this->meta['images']]);
    }
}
