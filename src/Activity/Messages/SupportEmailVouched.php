<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class SupportEmailVouched extends MessageAbstract
{
    public const string TYPE = 'core.support_email_vouched';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('request_id');
        $this->ensureIsNumeric('request_id');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_support_email_vouched', [
            '%request%' => '#' . (int) $this->meta['request_id'],
        ]);
    }

    protected function renderHtml(): string
    {
        $id = (int) $this->meta['request_id'];
        $link = sprintf('<a href="%s">#%d</a>', $this->router->generate('app_admin_support_request_show', ['id' => $id]), $id);

        return $this->translator->trans('profile_social.activity_support_email_vouched', ['%request%' => $link]);
    }
}
