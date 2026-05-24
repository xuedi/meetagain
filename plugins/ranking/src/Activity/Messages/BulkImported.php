<?php declare(strict_types=1);

namespace Plugin\Ranking\Activity\Messages;

use App\Activity\MessageAbstract;

class BulkImported extends MessageAbstract
{
    public const string TYPE = 'ranking.bulk_imported';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('group_id');
        $this->ensureIsNumeric('group_id');
        $this->ensureHasKey('count');
        $this->ensureIsNumeric('count');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('ranking_activity.bulk_imported', [
            '%count%' => (string) $this->meta['count'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->escapeHtml($this->renderText());
    }
}
