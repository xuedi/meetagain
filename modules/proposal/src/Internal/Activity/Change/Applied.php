<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Activity\Change;

use App\Activity\MessageAbstract;

class Applied extends MessageAbstract
{
    public const string TYPE = 'core.change_proposal_applied';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('target_label');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_change_proposal_applied', [
            '%target%' => $this->meta['target_label'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_change_proposal_applied', [
            '%target%' => '<strong>' . $this->escapeHtml($this->meta['target_label']) . '</strong>',
        ]);
    }
}
