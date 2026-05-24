<?php declare(strict_types=1);

namespace Plugin\Ranking\Activity\Messages;

use App\Activity\MessageAbstract;
use Plugin\Ranking\Enum\RankChangeReason;

class MemberRankChanged extends MessageAbstract
{
    public const string TYPE = 'ranking.member_rank_changed';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');
        $this->ensureHasKey('group_id');
        $this->ensureIsNumeric('group_id');
        $this->ensureHasKey('reason');
        $this->ensureHasKey('new');

        return $this;
    }

    protected function renderText(): string
    {
        $reason = (string) ($this->meta['reason'] ?? '');
        $isSelf = $reason === RankChangeReason::SelfEdit->value;
        $key = $isSelf ? 'ranking_activity.rank_changed_self' : 'ranking_activity.rank_changed_admin';

        return $this->translator->trans($key, [
            '%user_id%' => (string) $this->meta['user_id'],
            '%old%' => (string) ($this->meta['old'] ?? '-'),
            '%new%' => (string) ($this->meta['new'] ?? '-'),
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->escapeHtml($this->renderText());
    }
}
