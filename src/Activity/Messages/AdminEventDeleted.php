<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminEventDeleted extends MessageAbstract
{
    public const string TYPE = 'core.admin_event_deleted';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');
        $this->ensureHasKey('event_name');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Deleted event: %s', $this->meta['event_name']);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
