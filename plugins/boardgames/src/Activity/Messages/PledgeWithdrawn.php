<?php declare(strict_types=1);

namespace Plugin\Boardgames\Activity\Messages;

use App\Activity\MessageAbstract;

class PledgeWithdrawn extends MessageAbstract
{
    public const string TYPE = 'boardgames.pledge_withdrawn';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('game_id');
        $this->ensureIsNumeric('game_id');
        $this->ensureHasKey('game_name');
        $this->ensureHasKey('event_id');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('boardgames_activity.pledge_withdrawn', ['%game%' => $this->meta['game_name']]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('boardgames_activity.pledge_withdrawn', [
            '%game%' => '<strong>' . $this->escapeHtml($this->meta['game_name']) . '</strong>',
        ]);
    }
}
