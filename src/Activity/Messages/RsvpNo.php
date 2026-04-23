<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class RsvpNo extends MessageAbstract
{
    public const string TYPE = 'core.rsvp_no';

    public function getType(): string
    {
        return self::TYPE;
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
        $eventName = $this->eventNames[$eventId] ?? null;
        if ($eventName === null) {
            return $this->translator->trans('profile_social.activity_rsvp_no_deleted');
        }

        return $this->translator->trans('profile_social.activity_rsvp_no', ['%event%' => $eventName]);
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? null;
        if ($eventName === null) {
            return $this->translator->trans('profile_social.activity_rsvp_no_deleted');
        }

        $link = sprintf('<a href="%s">%s</a>', $this->router->generate('app_event_details', ['id' => $eventId]), $this->escapeHtml($eventName));

        return $this->translator->trans('profile_social.activity_rsvp_no', ['%event%' => $link]);
    }
}
