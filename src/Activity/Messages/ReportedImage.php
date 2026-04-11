<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;
use App\Enum\ImageReportReason;

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
            throw new \InvalidArgumentException("Value 'remarks' must be a string in '" . $this->getType() . "'");
        }

        return $this;
    }

    protected function renderText(): string
    {
        $text = sprintf('Reported image for reason: %s', ImageReportReason::from($this->meta['reason'])->name);
        if (isset($this->meta['remarks']) && $this->meta['remarks'] !== '') {
            $text .= sprintf(' - Remarks: %s', $this->meta['remarks']);
        }

        return $text;
    }

    protected function renderHtml(): string
    {
        $text = sprintf('Reported image for reason: <b>%s</b>', ImageReportReason::from($this->meta['reason'])->name);
        if (isset($this->meta['remarks']) && $this->meta['remarks'] !== '') {
            $text .= sprintf(' &mdash; Remarks: %s', htmlspecialchars($this->meta['remarks']));
        }

        return $text;
    }
}
