<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminEventEdited extends MessageAbstract
{
    public const string TYPE = 'core.admin_event_edited';

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
        $eventName = $this->eventNames[$eventId] ?? '[deleted]';

        return sprintf('Edited event: %s', $eventName);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
