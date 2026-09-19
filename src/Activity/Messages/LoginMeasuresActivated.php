<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class LoginMeasuresActivated extends MessageAbstract
{
    public const string TYPE = 'core.login_measures_activated';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('ip');
        $this->ensureHasKey('attempts');
        $this->ensureIsNumeric('attempts');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_login_measures_activated', [
            '%ip%' => (string) $this->meta['ip'],
            '%attempts%' => (int) $this->meta['attempts'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_login_measures_activated', [
            '%ip%' => $this->escapeHtml((string) $this->meta['ip']),
            '%attempts%' => (int) $this->meta['attempts'],
        ]);
    }
}
