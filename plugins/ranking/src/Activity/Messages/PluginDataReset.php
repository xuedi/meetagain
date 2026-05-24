<?php declare(strict_types=1);

namespace Plugin\Ranking\Activity\Messages;

use App\Activity\MessageAbstract;

class PluginDataReset extends MessageAbstract
{
    public const string TYPE = 'ranking.plugin_data_reset';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('group_id');
        $this->ensureIsNumeric('group_id');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('ranking_activity.plugin_data_reset');
    }

    protected function renderHtml(): string
    {
        return $this->escapeHtml($this->renderText());
    }
}
