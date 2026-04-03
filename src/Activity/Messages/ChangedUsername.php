<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class ChangedUsername extends MessageAbstract
{
    public const string TYPE = 'core.changed_username';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('old');
        $this->ensureHasKey('new');

        return $this;
    }

    protected function renderText(): string
    {
        $msgTemplate = 'Changed username from %s to %s';

        return sprintf($msgTemplate, $this->meta['old'], $this->meta['new']);
    }

    protected function renderHtml(): string
    {
        $msgTemplate = 'Changed username from <b>%s</b> to <b>%s</b>';

        return sprintf($msgTemplate, $this->escapeHtml($this->meta['old']), $this->escapeHtml($this->meta['new']));
    }
}
