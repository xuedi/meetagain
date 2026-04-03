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

        return $this;
    }

    protected function renderText(): string
    {
        $msgTemplate = 'Reported image for reason: %s';

        return sprintf($msgTemplate, ImageReportReason::from($this->meta['reason'])->name);
    }

    protected function renderHtml(): string
    {
        $msgTemplate = 'Reported image for reason: <b>%s</b>';

        return sprintf($msgTemplate, ImageReportReason::from($this->meta['reason'])->name);
    }
}
