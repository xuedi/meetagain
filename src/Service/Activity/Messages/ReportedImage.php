<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;
use App\Entity\ImageReported;

class ReportedImage extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::ReportedImage;
    }

    public function validate(): bool
    {
        $this->ensureHasKey('image_id');
        $this->ensureIsNumeric('image_id');
        $this->ensureHasKey('reason');
        $this->ensureIsNumeric('reason');

        return true;
    }

    protected function renderText(): string
    {
        $msgTemplate = 'Reported image for reason: %s';
        return sprintf(
            $msgTemplate,
            ImageReported::from($this->meta['reason'])->name
        );
    }

    protected function renderHtml(): string
    {
        $msgTemplate = 'Reported image for reason: <b>%s</b>';
        return sprintf(
            $msgTemplate,
            ImageReported::from($this->meta['reason'])->name
        );
    }
}
