<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class AdminCmsPageCreated extends MessageAbstract
{
    public const string TYPE = 'core.admin_cms_page_created';

    public function getType(): string
    {
        return self::TYPE;
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
