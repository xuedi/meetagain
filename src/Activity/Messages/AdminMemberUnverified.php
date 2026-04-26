<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminMemberUnverified extends MessageAbstract
{
    public const string TYPE = 'core.admin_member_unverified';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_admin_member_unverified', [
            '%user%' => $this->userNames[$this->meta['user_id']] ?? '',
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
