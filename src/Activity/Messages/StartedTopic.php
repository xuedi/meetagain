<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class StartedTopic extends MessageAbstract
{
    public const string TYPE = 'core.started_topic';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('topic_id');
        $this->ensureIsNumeric('topic_id');
        $this->ensureHasKey('topic_title');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_started_topic', ['%topic%' => $this->meta['topic_title']]);
    }

    protected function renderHtml(): string
    {
        $title = sprintf('<strong>%s</strong>', $this->escapeHtml((string) $this->meta['topic_title']));

        return $this->translator->trans('profile_social.activity_started_topic', ['%topic%' => $title]);
    }
}
