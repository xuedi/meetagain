<?php declare(strict_types=1);

namespace Module\Proposal\Internal\Activity\Suggestion;

use App\Activity\MessageAbstract;

class Approved extends MessageAbstract
{
    public const string TYPE = 'core.suggestion_approved';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('description');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('profile_social.activity_suggestion_approved', [
            '%target%' => $this->meta['description'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_suggestion_approved', [
            '%target%' => '<strong>' . $this->escapeHtml($this->meta['description']) . '</strong>',
        ]);
    }
}
