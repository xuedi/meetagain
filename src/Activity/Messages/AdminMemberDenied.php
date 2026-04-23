<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminMemberDenied extends MessageAbstract
{
    public const string TYPE = 'core.admin_member_denied';

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
        $userId = $this->meta['user_id'];
        $userName = $this->userNames[$userId] ?? null;
        if ($userName === null) {
            return $this->translator->trans('profile_social.activity_admin_member_denied_deleted');
        }

        return $this->translator->trans('profile_social.activity_admin_member_denied', ['%user%' => $userName]);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
