<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;
use App\Enum\ModerationReportReason;
use InvalidArgumentException;

class ReportedSubject extends MessageAbstract
{
    public const string TYPE = 'core.reported_subject';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('subject_type');
        $this->ensureHasKey('subject_id');
        $this->ensureIsNumeric('subject_id');
        $this->ensureHasKey('reason');

        if (isset($this->meta['remarks']) && !is_string($this->meta['remarks'])) {
            throw new InvalidArgumentException("Value 'remarks' must be a string in '" . $this->getType() . "'");
        }

        return $this;
    }

    protected function renderText(): string
    {
        return $this->compose($this->reasonLabel(), $this->meta['remarks'] ?? '');
    }

    protected function renderHtml(): string
    {
        return $this->compose('<b>' . $this->escapeHtml($this->reasonLabel()) . '</b>', $this->escapeHtml((string) ($this->meta['remarks'] ?? '')));
    }

    private function compose(string $reason, string $remarks): string
    {
        $text = $this->translator->trans('profile_social.activity_reported_subject', ['%reason%' => $reason]);
        if ($remarks === '') {
            return $text;
        }

        return $this->translator->trans('profile_social.activity_reported_subject_remarks', [
            '%message%' => $text,
            '%remarks%' => $remarks,
        ]);
    }

    private function reasonLabel(): string
    {
        $reason = ModerationReportReason::tryFrom((string) $this->meta['reason']);

        return $reason === null ? (string) $this->meta['reason'] : $this->translator->trans($reason->label());
    }
}
