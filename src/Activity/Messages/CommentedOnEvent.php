<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class CommentedOnEvent extends MessageAbstract
{
    public const string TYPE = 'core.commented_on_event';

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

        return sprintf('commented on event: %s', $eventName);
    }

    protected function renderHtml(): string
    {
        $eventId = $this->meta['event_id'];
        $eventName = $this->eventNames[$eventId] ?? '[deleted]';
        if ($eventName === '[deleted]') {
            return 'commented on event [deleted]';
        }

        return sprintf(
            'commented on event <a href="%s">%s</a>',
            $this->router->generate('app_event_details', ['id' => $eventId]),
            $this->escapeHtml($eventName),
        );
    }
}
