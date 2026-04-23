<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;
use App\Enum\ImageReportReason;
use InvalidArgumentException;

class ReportedImage extends MessageAbstract
{
    public const string TYPE = 'core.reported_image';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('image_id');
        $this->ensureIsNumeric('image_id');
        $this->ensureHasKey('reason');
        $this->ensureIsNumeric('reason');

        if (isset($this->meta['remarks']) && !is_string($this->meta['remarks'])) {
            throw new InvalidArgumentException("Value 'remarks' must be a string in '" . $this->getType() . "'");
        }

        return $this;
    }

    protected function renderText(): string
    {
        $reasonName = ImageReportReason::from($this->meta['reason'])->name;
        $text = $this->translator->trans('profile_social.activity_reported_image', ['%reason%' => $reasonName]);
        if (isset($this->meta['remarks']) && $this->meta['remarks'] !== '') {
            $text = $this->translator->trans('profile_social.activity_reported_image_remarks', ['%message%' => $text, '%remarks%' => $this->meta['remarks']]);
        }

        return $text;
    }

    protected function renderHtml(): string
    {
        $reasonName = ImageReportReason::from($this->meta['reason'])->name;
        $text = $this->translator->trans('profile_social.activity_reported_image', ['%reason%' => '<b>' . $this->escapeHtml($reasonName) . '</b>']);
        if (isset($this->meta['remarks']) && $this->meta['remarks'] !== '') {
            $text = $this->translator->trans('profile_social.activity_reported_image_remarks', ['%message%' => $text, '%remarks%' => $this->escapeHtml($this->meta['remarks'])]);
        }

        return $text;
    }
}
