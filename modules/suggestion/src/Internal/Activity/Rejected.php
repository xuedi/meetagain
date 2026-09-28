<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Activity;

use App\Activity\MessageAbstract;

class Rejected extends MessageAbstract
{
    public const string TYPE = 'core.suggestion_rejected';

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
        return $this->translator->trans('profile_social.activity_suggestion_rejected', [
            '%target%' => $this->meta['description'],
        ]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_suggestion_rejected', [
            '%target%' => '<strong>' . $this->escapeHtml($this->meta['description']) . '</strong>',
        ]);
    }
}
