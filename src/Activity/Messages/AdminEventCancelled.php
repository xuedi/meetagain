<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminEventCancelled extends MessageAbstract
{
    public const string TYPE = 'core.admin_event_cancelled';

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
            return $this->translator->trans('profile_social.activity_admin_event_cancelled_deleted');
        }

        return $this->translator->trans('profile_social.activity_admin_event_cancelled', ['%event%' => $eventName]);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
