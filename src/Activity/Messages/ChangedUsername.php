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
        return $this->translator->trans('profile_social.activity_changed_username', ['%old%' => $this->meta['old'], '%new%' => $this->meta['new']]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_changed_username', [
            '%old%' => '<b>' . $this->escapeHtml($this->meta['old']) . '</b>',
            '%new%' => '<b>' . $this->escapeHtml($this->meta['new']) . '</b>',
        ]);
    }
}
