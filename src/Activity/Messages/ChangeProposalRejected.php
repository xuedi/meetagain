<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class ChangeProposalRejected extends MessageAbstract
{
    public const string TYPE = 'core.change_proposal_rejected';

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
        return $this->translator->trans('profile_social.activity_change_proposal_rejected', [
            '%target%' => $this->meta['target_label'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_change_proposal_rejected', [
            '%target%' => '<strong>' . $this->escapeHtml($this->meta['target_label']) . '</strong>',
        ]);
    }
}
