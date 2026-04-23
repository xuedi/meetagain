<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class SendMessage extends MessageAbstract
{
    public const string TYPE = 'core.send_message';

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
            return $this->translator->trans('profile_social.activity_send_message_deleted');
        }

        return $this->translator->trans('profile_social.activity_send_message', ['%user%' => $userName]);
    }

    protected function renderHtml(): string
    {
        $userId = $this->meta['user_id'];
        $userName = $this->userNames[$userId] ?? null;
        if ($userName === null) {
            return $this->translator->trans('profile_social.activity_send_message_deleted');
        }

        $link = sprintf('<a href="%s">%s</a>', $this->router->generate('app_member_view', ['id' => $userId]), $this->escapeHtml($userName));

        return $this->translator->trans('profile_social.activity_send_message', ['%user%' => $link]);
    }
}
