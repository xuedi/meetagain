<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class UnblockedUser extends MessageAbstract
{
    public const string TYPE = 'core.unblocked_user';

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
        $userName = $this->userNames[$userId] ?? '[deleted]';

        return sprintf('Unblocked user: %s', $userName);
    }

    protected function renderHtml(): string
    {
        $userId = $this->meta['user_id'];
        $userName = $this->userNames[$userId] ?? '[deleted]';

        return sprintf('Unblocked user: %s', $this->escapeHtml($userName));
    }
}
