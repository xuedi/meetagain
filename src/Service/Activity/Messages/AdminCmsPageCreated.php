<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageAbstract;

class AdminCmsPageCreated extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::AdminCmsPageCreated;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('cms_id');
        $this->ensureIsNumeric('cms_id');
        $this->ensureHasKey('cms_slug');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Created CMS page: %s', $this->meta['cms_slug']);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
