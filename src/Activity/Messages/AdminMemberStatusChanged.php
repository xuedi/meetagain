<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;
use App\Enum\UserStatus;

class AdminMemberStatusChanged extends MessageAbstract
{
    public const string TYPE = 'core.admin_member_status_changed';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');
        $this->ensureHasKey('old');
        $this->ensureIsNumeric('old');
        $this->ensureHasKey('new');
        $this->ensureIsNumeric('new');

        return $this;
    }

    protected function renderText(): string
    {
        $userId = $this->meta['user_id'];
        $old = $this->translator->trans(UserStatus::from((int) $this->meta['old'])->label());
        $new = $this->translator->trans(UserStatus::from((int) $this->meta['new'])->label());

        return $this->translator->trans('profile_social.activity_admin_member_status_changed', [
            '%user%' => $this->userNames[$userId] ?? '',
            '%old%' => $old,
            '%new%' => $new,
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
